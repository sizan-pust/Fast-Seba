import 'package:flutter/material.dart';
import '../core/app_state.dart';

class ProductDetailScreen extends StatelessWidget {
  const ProductDetailScreen(
      {super.key, required this.state, required this.product});
  final AppState state;
  final Map<String, dynamic> product;
  @override
  Widget build(BuildContext context) {
    final variants = product['variants'];
    final variant = variants is List && variants.isNotEmpty
        ? Map<String, dynamic>.from(variants.first as Map)
        : <String, dynamic>{};
    return Scaffold(
      appBar: AppBar(title: const Text('Product details')),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        Container(
            height: 260,
            decoration: BoxDecoration(
                color: const Color(0xFFE7F5ED),
                borderRadius: BorderRadius.circular(28)),
            child: const Icon(Icons.medication_rounded,
                size: 110, color: Color(0xFF137A4B))),
        const SizedBox(height: 24),
        Text(product['title']?.toString() ?? '',
            style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w900)),
        const SizedBox(height: 8),
        Text(variant['title']?.toString() ?? '',
            style: const TextStyle(color: Colors.black54)),
        const SizedBox(height: 16),
        Text('৳${variant['special_price'] ?? variant['price'] ?? '0.00'}',
            style: const TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.w900,
                color: Color(0xFF137A4B))),
        const SizedBox(height: 20),
        Text(
            product['short_description']?.toString() ??
                'Healthcare product available from a nearby verified store.',
            style: const TextStyle(height: 1.5)),
      ]),
      bottomNavigationBar: SafeArea(
          child: Padding(
              padding: const EdgeInsets.all(16),
              child: FilledButton.icon(
                onPressed: () async {
                  try {
                    await state.addToCart(product);
                    if (context.mounted)
                      ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Added to cart')));
                  } catch (e) {
                    if (context.mounted)
                      ScaffoldMessenger.of(context)
                          .showSnackBar(SnackBar(content: Text(e.toString())));
                  }
                },
                icon: const Icon(Icons.shopping_bag_outlined),
                label: const Padding(
                    padding: EdgeInsets.all(14), child: Text('Add to cart')),
              ))),
    );
  }
}
