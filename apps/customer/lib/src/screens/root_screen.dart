import 'package:flutter/material.dart';
import '../core/app_state.dart';
import 'home_screen.dart';
import 'cart_screen.dart';
import 'orders_screen.dart';
import 'account_screen.dart';

class RootScreen extends StatefulWidget {
  const RootScreen({super.key, required this.state});
  final AppState state;
  @override
  State<RootScreen> createState() => _RootScreenState();
}

class _RootScreenState extends State<RootScreen> {
  int index = 0;
  bool booting = true;
  @override
  void initState() {
    super.initState();
    widget.state.addListener(_refresh);
    widget.state.bootstrap().whenComplete(() {
      if (mounted) setState(() => booting = false);
    });
  }

  @override
  void dispose() {
    widget.state.removeListener(_refresh);
    super.dispose();
  }

  void _refresh() => mounted ? setState(() {}) : null;
  @override
  Widget build(BuildContext context) {
    if (booting) {
      return const Scaffold(
        body: Center(
            child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.local_pharmacy_rounded,
                size: 72, color: Color(0xFF137A4B)),
            SizedBox(height: 16),
            Text('FastSheba',
                style: TextStyle(fontSize: 28, fontWeight: FontWeight.w800)),
            SizedBox(height: 20),
            CircularProgressIndicator(),
          ],
        )),
      );
    }
    final screens = [
      HomeScreen(state: widget.state),
      CartScreen(state: widget.state),
      OrdersScreen(state: widget.state),
      AccountScreen(state: widget.state),
    ];
    return Scaffold(
      body: IndexedStack(index: index, children: screens),
      bottomNavigationBar: NavigationBar(
        selectedIndex: index,
        onDestinationSelected: (value) {
          setState(() => index = value);
          if (value == 1) widget.state.loadCart();
          if (value == 2) widget.state.loadOrders();
        },
        destinations: const [
          NavigationDestination(
              icon: Icon(Icons.home_outlined),
              selectedIcon: Icon(Icons.home),
              label: 'Home'),
          NavigationDestination(
              icon: Icon(Icons.shopping_bag_outlined),
              selectedIcon: Icon(Icons.shopping_bag),
              label: 'Cart'),
          NavigationDestination(
              icon: Icon(Icons.receipt_long_outlined),
              selectedIcon: Icon(Icons.receipt_long),
              label: 'Orders'),
          NavigationDestination(
              icon: Icon(Icons.person_outline),
              selectedIcon: Icon(Icons.person),
              label: 'Account'),
        ],
      ),
    );
  }
}
