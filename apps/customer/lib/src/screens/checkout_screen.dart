import 'package:flutter/material.dart';

import '../core/app_state.dart';
import 'address_form_screen.dart';
import 'order_detail_screen.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({
    super.key,
    required this.state,
  });

  final AppState state;

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  Map<String, dynamic>? selectedAddress;
  String paymentType = 'cod';
  bool useWallet = false;
  bool busy = false;
  String? error;

  final promo = TextEditingController();
  final note = TextEditingController();

  @override
  void initState() {
    super.initState();
    prepare();
  }

  @override
  void dispose() {
    promo.dispose();
    note.dispose();
    super.dispose();
  }

  int? get selectedAddressId {
    final value = selectedAddress?['id'];

    if (value is int) {
      return value;
    }

    return int.tryParse(
      value?.toString() ?? '',
    );
  }

  Future<void> prepare() async {
    try {
      await widget.state.loadAddresses();
      await widget.state.loadWallet();
      await widget.state.loadPromos();

      if (widget.state.addresses.isNotEmpty) {
        selectedAddress = widget.state.addresses.firstWhere(
          (address) => address['is_default'] == true,
          orElse: () => widget.state.addresses.first,
        );

        await refreshSummary();
      }
    } catch (exception) {
      error = exception.toString();
    }

    if (mounted) {
      setState(() {});
    }
  }

  Future<void> addAddress() async {
    final address = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(
        builder: (_) => AddressFormScreen(
          state: widget.state,
        ),
      ),
    );

    if (address != null) {
      selectedAddress = address;
      await refreshSummary();

      if (mounted) {
        setState(() {});
      }
    }
  }

  Future<void> refreshSummary() async {
    final addressId = selectedAddressId;

    if (addressId == null) {
      return;
    }

    try {
      await widget.state.loadCart(
        addressId: addressId,
        promoCode: promo.text,
        useWallet: useWallet,
      );

      error = null;
    } catch (exception) {
      error = exception.toString();
    }

    if (mounted) {
      setState(() {});
    }
  }

  Future<void> placeOrder() async {
    final addressId = selectedAddressId;

    if (addressId == null) {
      setState(() {
        error = 'Select or add a delivery address.';
      });
      return;
    }

    setState(() {
      busy = true;
      error = null;
    });

    try {
      final order = await widget.state.placeOrder(
        addressId: addressId,
        paymentType: paymentType,
        promoCode: promo.text,
        useWallet: useWallet,
        note: note.text.trim(),
      );

      if (!mounted) {
        return;
      }

      await Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => OrderDetailScreen(
            state: widget.state,
            orderSlug: order['slug']?.toString() ?? '',
            initialOrder: order,
          ),
        ),
      );
    } catch (exception) {
      if (mounted) {
        setState(() {
          error = exception.toString();
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          busy = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final summary = widget.state.cart?['payment_summary'] as Map?;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Checkout'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          const Text(
            'Delivery address',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 10),
          if (selectedAddress == null)
            OutlinedButton.icon(
              onPressed: addAddress,
              icon: const Icon(
                Icons.add_location_alt_outlined,
              ),
              label: const Padding(
                padding: EdgeInsets.all(14),
                child: Text(
                  'Add delivery address',
                ),
              ),
            )
          else
            Card(
              child: ListTile(
                onTap: addAddress,
                leading: const CircleAvatar(
                  child: Icon(
                    Icons.location_on_outlined,
                  ),
                ),
                title: Text(
                  selectedAddress!['address_line1']?.toString() ?? '',
                ),
                subtitle: Text(
                  [
                    selectedAddress!['landmark'],
                    selectedAddress!['city'],
                    selectedAddress!['mobile'],
                  ]
                      .where(
                        (value) =>
                            value != null && value.toString().trim().isNotEmpty,
                      )
                      .join(' • '),
                ),
                trailing: const Icon(
                  Icons.edit_outlined,
                ),
              ),
            ),
          const SizedBox(height: 22),
          const Text(
            'Promo code',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: promo,
                  decoration: const InputDecoration(
                    hintText: 'FAST10',
                    border: OutlineInputBorder(),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              FilledButton.tonal(
                onPressed: refreshSummary,
                child: const Padding(
                  padding: EdgeInsets.symmetric(
                    vertical: 15,
                  ),
                  child: Text('Apply'),
                ),
              ),
            ],
          ),
          if (widget.state.promos.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                'Available: ${widget.state.promos.map((item) => item['code']).whereType<Object>().join(', ')}',
                style: const TextStyle(
                  color: Colors.black54,
                ),
              ),
            ),
          const SizedBox(height: 22),
          const Text(
            'Payment',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 10),
          SegmentedButton<String>(
            segments: const [
              ButtonSegment<String>(
                value: 'cod',
                icon: Icon(
                  Icons.payments_outlined,
                ),
                label: Text('COD'),
              ),
              ButtonSegment<String>(
                value: 'wallet',
                icon: Icon(
                  Icons.account_balance_wallet_outlined,
                ),
                label: Text('Wallet'),
              ),
            ],
            selected: {paymentType},
            onSelectionChanged: (selection) {
              final selected = selection.first;

              setState(() {
                paymentType = selected;

                if (selected == 'wallet') {
                  useWallet = true;
                }
              });

              refreshSummary();
            },
          ),
          const SizedBox(height: 8),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            value: useWallet,
            onChanged: (value) {
              setState(() {
                useWallet = value;
              });

              refreshSummary();
            },
            title: Text(
              'Use wallet first • '
              '৳${widget.state.wallet?['balance'] ?? widget.state.wallet?['available_balance'] ?? '0.00'}',
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: note,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: 'Order note',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 18),
          if (summary != null)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  children: [
                    _summaryRow(
                      'Items',
                      summary['items_total'],
                    ),
                    _summaryRow(
                      'Delivery',
                      summary['delivery_charges'],
                    ),
                    _summaryRow(
                      'Promo discount',
                      '-${summary['promo_discount'] ?? '0.00'}',
                    ),
                    _summaryRow(
                      'Wallet',
                      '-${summary['wallet_amount_used'] ?? '0.00'}',
                    ),
                    const Divider(),
                    _summaryRow(
                      'Payable',
                      summary['payable_amount'],
                      bold: true,
                    ),
                  ],
                ),
              ),
            ),
          if (error != null)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text(
                error!,
                style: const TextStyle(
                  color: Colors.red,
                ),
              ),
            ),
          const SizedBox(height: 20),
          FilledButton(
            onPressed: busy ? null : placeOrder,
            child: Padding(
              padding: const EdgeInsets.all(15),
              child: busy
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                      ),
                    )
                  : const Text('Place order'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _summaryRow(
    String label,
    Object? value, {
    bool bold = false,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(
        vertical: 6,
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              fontWeight: bold ? FontWeight.w800 : null,
            ),
          ),
          Text(
            '৳${value ?? '0.00'}',
            style: TextStyle(
              fontWeight: bold ? FontWeight.w900 : FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}
