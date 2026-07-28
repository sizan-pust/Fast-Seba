import 'package:flutter/material.dart';

import '../core/app_state.dart';
import 'order_detail_screen.dart';

class OrdersScreen extends StatelessWidget {
  const OrdersScreen({
    super.key,
    required this.state,
  });

  final AppState state;

  @override
  Widget build(BuildContext context) {
    if (!state.signedIn) {
      return const SafeArea(
        child: Center(
          child: Text(
            'Sign in to view your orders.',
          ),
        ),
      );
    }

    return SafeArea(
      child: RefreshIndicator(
        onRefresh: state.loadOrders,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            const Text(
              'My orders',
              style: TextStyle(
                fontSize: 28,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 18),
            if (state.orders.isEmpty)
              const Padding(
                padding: EdgeInsets.only(top: 100),
                child: Center(
                  child: Text(
                    'No orders yet.',
                  ),
                ),
              ),
            ...state.orders.map(
              (order) => Padding(
                padding: const EdgeInsets.only(
                  bottom: 12,
                ),
                child: Card(
                  child: ListTile(
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => OrderDetailScreen(
                          state: state,
                          orderSlug: order['slug']?.toString() ?? '',
                          initialOrder: order,
                        ),
                      ),
                    ),
                    contentPadding: const EdgeInsets.all(
                      16,
                    ),
                    leading: const CircleAvatar(
                      child: Icon(
                        Icons.receipt_long,
                      ),
                    ),
                    title: Text(
                      order['slug']?.toString() ?? '',
                    ),
                    subtitle: Text(
                      order['status_label']?.toString() ??
                          order['status']?.toString() ??
                          '',
                    ),
                    trailing: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          '৳${order['final_total'] ?? '0.00'}',
                          style: const TextStyle(
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const Icon(
                          Icons.chevron_right,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
