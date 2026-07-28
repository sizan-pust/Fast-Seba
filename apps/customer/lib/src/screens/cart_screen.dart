import 'package:flutter/material.dart';

import '../core/app_state.dart';
import 'checkout_screen.dart';

class CartScreen extends StatelessWidget {
  const CartScreen({
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
            'Sign in to view your cart.',
          ),
        ),
      );
    }

    final items = (state.cart?['items'] as List?) ?? const [];

    final summary = state.cart?['payment_summary'] as Map?;

    return SafeArea(
      child: RefreshIndicator(
        onRefresh: () => state.loadCart(),
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            const Text(
              'Your cart',
              style: TextStyle(
                fontSize: 28,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 18),
            if (items.isEmpty)
              const Padding(
                padding: EdgeInsets.only(top: 100),
                child: Center(
                  child: Text(
                    'Your cart is empty.',
                  ),
                ),
              ),
            ...items.whereType<Map>().map(
              (raw) {
                final item = Map<String, dynamic>.from(
                  raw,
                );

                final product = item['product'] as Map?;

                final variant = item['variant'] as Map?;

                return Padding(
                  padding: const EdgeInsets.only(
                    bottom: 12,
                  ),
                  child: Card(
                    child: ListTile(
                      contentPadding: const EdgeInsets.all(
                        14,
                      ),
                      leading: const CircleAvatar(
                        child: Icon(
                          Icons.medication,
                        ),
                      ),
                      title: Text(
                        product?['title']?.toString() ?? '',
                      ),
                      subtitle: Text(
                        '${variant?['title'] ?? ''}'
                        ' • Qty '
                        '${item['quantity']}',
                      ),
                      trailing: Text(
                        '৳${item['total_item_special_price'] ?? '0.00'}',
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ),
                );
              },
            ),
            if (summary != null)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(
                    18,
                  ),
                  child: Column(
                    children: [
                      _row(
                        'Items',
                        summary['items_total'],
                      ),
                      _row(
                        'Delivery',
                        summary['delivery_charges'],
                      ),
                      _row(
                        'Discount',
                        '-${summary['promo_discount'] ?? '0.00'}',
                      ),
                      const Divider(),
                      _row(
                        'Payable',
                        summary['payable_amount'],
                        bold: true,
                      ),
                    ],
                  ),
                ),
              ),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: items.isEmpty
                  ? null
                  : () async {
                      await Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => CheckoutScreen(
                            state: state,
                          ),
                        ),
                      );
                    },
              child: const Padding(
                padding: EdgeInsets.all(14),
                child: Text(
                  'Proceed to checkout',
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _row(
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
