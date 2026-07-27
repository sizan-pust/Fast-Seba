import 'package:flutter/material.dart';
import '../core/app_state.dart';
import 'product_detail_screen.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key, required this.state});
  final AppState state;
  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: RefreshIndicator(
        onRefresh: state.loadHome,
        child: CustomScrollView(
          slivers: [
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(20, 18, 20, 8),
              sliver: SliverToBoxAdapter(child: Row(
                children: [
                  const Expanded(child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Delivering healthcare', style: TextStyle(color: Colors.black54)),
                      Text('FastSheba', style: TextStyle(fontSize: 28, fontWeight: FontWeight.w900)),
                    ],
                  )),
                  IconButton.filledTonal(onPressed: () {}, icon: const Icon(Icons.notifications_none)),
                ],
              )),
            ),
            SliverPadding(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
              sliver: SliverToBoxAdapter(child: TextField(
                decoration: InputDecoration(
                  hintText: 'Search medicine, brand or category',
                  prefixIcon: const Icon(Icons.search),
                  filled: true,
                  fillColor: Colors.white,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(18), borderSide: BorderSide.none),
                ),
              )),
            ),
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(20, 14, 20, 8),
              sliver: const SliverToBoxAdapter(child: Text('Categories', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800))),
            ),
            SliverToBoxAdapter(
              child: SizedBox(
                height: 110,
                child: ListView.separated(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  scrollDirection: Axis.horizontal,
                  itemCount: state.categories.length,
                  separatorBuilder: (_, __) => const SizedBox(width: 12),
                  itemBuilder: (_, index) {
                    final category = state.categories[index];
                    return Container(
                      width: 104,
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(color: const Color(0xFFE7F5ED), borderRadius: BorderRadius.circular(20)),
                      child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                        const Icon(Icons.medication_outlined, color: Color(0xFF137A4B), size: 30),
                        const SizedBox(height: 8),
                        Text(category['title']?.toString() ?? '', maxLines: 2, textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.w700)),
                      ]),
                    );
                  },
                ),
              ),
            ),
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(20, 18, 20, 10),
              sliver: const SliverToBoxAdapter(child: Text('Popular near you', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800))),
            ),
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 30),
              sliver: SliverGrid.builder(
                itemCount: state.products.length,
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 2, crossAxisSpacing: 12, mainAxisSpacing: 12, childAspectRatio: .68),
                itemBuilder: (_, index) {
                  final product = state.products[index];
                  final variants = product['variants'];
                  final variant = variants is List && variants.isNotEmpty ? Map<String, dynamic>.from(variants.first as Map) : <String, dynamic>{};
                  return InkWell(
                    borderRadius: BorderRadius.circular(18),
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => ProductDetailScreen(state: state, product: product))),
                    child: Card(child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Expanded(child: Container(decoration: BoxDecoration(color: const Color(0xFFF0F4F2), borderRadius: BorderRadius.circular(14)), child: const Center(child: Icon(Icons.medication_rounded, size: 54, color: Color(0xFF137A4B))))),
                        const SizedBox(height: 12),
                        Text(product['title']?.toString() ?? '', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w800)),
                        const SizedBox(height: 6),
                        Text(variant['title']?.toString() ?? '', style: const TextStyle(color: Colors.black54, fontSize: 12)),
                        const Spacer(),
                        Row(children: [
                          Expanded(child: Text('৳${variant['special_price'] ?? variant['price'] ?? '0.00'}', style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w900))),
                          IconButton.filled(onPressed: () async {
                            try { await state.addToCart(product); if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Added to cart'))); }
                            catch (e) { if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString()))); }
                          }, icon: const Icon(Icons.add_shopping_cart, size: 18)),
                        ]),
                      ]),
                    )),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}
