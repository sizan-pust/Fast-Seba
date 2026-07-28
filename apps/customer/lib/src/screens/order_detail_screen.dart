import 'package:flutter/material.dart';

import '../core/app_state.dart';

class OrderDetailScreen extends StatefulWidget {
  const OrderDetailScreen({
    super.key,
    required this.state,
    required this.orderSlug,
    this.initialOrder,
  });

  final AppState state;
  final String orderSlug;
  final Map<String, dynamic>? initialOrder;

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  Map<String, dynamic>? order;
  Map<String, dynamic>? location;

  bool loading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    order = widget.initialOrder;
    refresh();
  }

  Future<void> refresh() async {
    try {
      final loaded = await widget.state.loadOrder(widget.orderSlug);

      Map<String, dynamic>? deliveryLocation;

      try {
        deliveryLocation = await widget.state.loadDeliveryLocation(
          widget.orderSlug,
        );
      } catch (_) {
        deliveryLocation = null;
      }

      if (mounted) {
        setState(() {
          order = loaded;
          location = deliveryLocation;
          loading = false;
          error = null;
        });
      }
    } catch (exception) {
      if (mounted) {
        setState(() {
          loading = false;
          error = exception.toString();
        });
      }
    }
  }

  Future<void> requestReturn(
    Map<String, dynamic> item,
  ) async {
    final controller = TextEditingController();

    final reason = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text(
          'Request return',
        ),
        content: TextField(
          controller: controller,
          maxLines: 3,
          decoration: const InputDecoration(
            hintText: 'Reason for return',
            border: OutlineInputBorder(),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(
              dialogContext,
            ),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(
              dialogContext,
              controller.text.trim(),
            ),
            child: const Text('Submit'),
          ),
        ],
      ),
    );

    controller.dispose();

    if (reason == null || reason.isEmpty) {
      return;
    }

    final itemId = item['id'];

    if (itemId is! int) {
      return;
    }

    try {
      await widget.state.requestReturn(
        orderItemId: itemId,
        reason: reason,
      );

      await refresh();

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Return request submitted.',
            ),
          ),
        );
      }
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              exception.toString(),
            ),
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final current = order ?? <String, dynamic>{};

    final items = (current['items'] as List?) ?? const [];

    final timeline = (current['status_timeline'] as List?) ?? const [];

    final rider = current['delivery_boy'] as Map?;

    return Scaffold(
      appBar: AppBar(
        title: Text(
          current['slug']?.toString() ?? 'Order',
        ),
      ),
      body: loading && order == null
          ? const Center(
              child: CircularProgressIndicator(),
            )
          : RefreshIndicator(
              onRefresh: refresh,
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  if (error != null)
                    Text(
                      error!,
                      style: const TextStyle(
                        color: Colors.red,
                      ),
                    ),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(
                        18,
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            current['status_label']?.toString() ??
                                current['status']?.toString() ??
                                '',
                            style: const TextStyle(
                              fontSize: 22,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(
                            height: 8,
                          ),
                          Text(
                            'Payment: '
                            '${current['payment_method'] ?? ''}'
                            ' • '
                            '${current['payment_status'] ?? ''}',
                          ),
                          const SizedBox(
                            height: 6,
                          ),
                          Text(
                            'Total: '
                            '৳${current['final_total'] ?? '0.00'}',
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                  if (rider != null)
                    Card(
                      child: ListTile(
                        leading: const CircleAvatar(
                          child: Icon(
                            Icons.delivery_dining,
                          ),
                        ),
                        title: Text(
                          rider['name']?.toString() ?? 'Delivery partner',
                        ),
                        subtitle: Text(
                          [
                            rider['mobile'],
                            rider['vehicle_type'],
                          ]
                              .where(
                                (value) =>
                                    value != null &&
                                    value.toString().isNotEmpty,
                              )
                              .join(' • '),
                        ),
                        trailing: location?['location'] != null
                            ? const Icon(
                                Icons.location_on,
                                color: Colors.green,
                              )
                            : null,
                      ),
                    ),
                  const SizedBox(height: 20),
                  const Text(
                    'Items',
                    style: TextStyle(
                      fontSize: 19,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 10),
                  ...items.whereType<Map>().map(
                    (raw) {
                      final item = Map<String, dynamic>.from(raw);

                      final product = item['product'] as Map?;

                      final orderItem = item['orderItem'] as Map?;

                      final returnRequest = item['return_request'] as Map?;

                      final canReturn = item['is_returnable'] == true &&
                          orderItem?['status'] == 'delivered' &&
                          returnRequest == null;

                      return Padding(
                        padding: const EdgeInsets.only(
                          bottom: 10,
                        ),
                        child: Card(
                          child: Padding(
                            padding: const EdgeInsets.all(14),
                            child: Column(
                              children: [
                                ListTile(
                                  contentPadding: EdgeInsets.zero,
                                  leading: const CircleAvatar(
                                    child: Icon(
                                      Icons.medication,
                                    ),
                                  ),
                                  title: Text(
                                    product?['title']?.toString() ?? '',
                                  ),
                                  subtitle: Text(
                                    '${orderItem?['status_label'] ?? ''}'
                                    ' • Qty '
                                    '${item['quantity']}',
                                  ),
                                  trailing: Text(
                                    '৳${item['sub_total'] ?? '0.00'}',
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                ),
                                if (returnRequest != null)
                                  Align(
                                    alignment: Alignment.centerLeft,
                                    child: Chip(
                                      label: Text(
                                        'Return: '
                                        '${returnRequest['return_status_label'] ?? returnRequest['return_status']}',
                                      ),
                                    ),
                                  ),
                                if (canReturn)
                                  Align(
                                    alignment: Alignment.centerRight,
                                    child: TextButton(
                                      onPressed: () => requestReturn(
                                        item,
                                      ),
                                      child: const Text(
                                        'Request return',
                                      ),
                                    ),
                                  ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                  const SizedBox(height: 20),
                  const Text(
                    'Tracking timeline',
                    style: TextStyle(
                      fontSize: 19,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 10),
                  if (timeline.isEmpty)
                    const Card(
                      child: ListTile(
                        leading: Icon(Icons.schedule),
                        title: Text('Order placed'),
                      ),
                    ),
                  ...timeline.whereType<Map>().map(
                    (raw) {
                      final event = Map<String, dynamic>.from(raw);

                      return ListTile(
                        leading: const Icon(
                          Icons.check_circle,
                          color: Colors.green,
                        ),
                        title: Text(
                          event['label']?.toString() ?? '',
                        ),
                        subtitle: Text(
                          [
                            event['note'],
                            event['created_at'],
                          ]
                              .where(
                                (value) =>
                                    value != null &&
                                    value.toString().isNotEmpty,
                              )
                              .join('\n'),
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),
    );
  }
}
