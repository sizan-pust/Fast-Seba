import 'package:flutter/material.dart';

import '../core/app_state.dart';
import 'address_list_screen.dart';
import 'login_screen.dart';

class AccountScreen extends StatelessWidget {
  const AccountScreen({
    super.key,
    required this.state,
  });

  final AppState state;

  @override
  Widget build(BuildContext context) {
    if (!state.signedIn) {
      return SafeArea(
        child: Center(
          child: FilledButton.icon(
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => LoginScreen(
                  state: state,
                ),
              ),
            ),
            icon: const Icon(Icons.login),
            label: const Text('Sign in'),
          ),
        ),
      );
    }

    final name = state.user?['name']?.toString() ?? 'User';

    return SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          const Text(
            'Account',
            style: TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 24),
          CircleAvatar(
            radius: 40,
            child: Text(
              name.isEmpty ? 'U' : name.substring(0, 1).toUpperCase(),
              style: const TextStyle(
                fontSize: 28,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          const SizedBox(height: 14),
          Center(
            child: Text(
              name,
              style: const TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          Center(
            child: Text(
              state.user?['email']?.toString() ?? '',
              style: const TextStyle(
                color: Colors.black54,
              ),
            ),
          ),
          const SizedBox(height: 28),
          Card(
            child: Column(
              children: [
                ListTile(
                  onTap: state.loadWallet,
                  leading: const Icon(
                    Icons.account_balance_wallet_outlined,
                  ),
                  title: const Text('Wallet'),
                  trailing: Text(
                    '৳${state.wallet?['balance'] ?? state.wallet?['available_balance'] ?? state.user?['wallet_balance'] ?? '0.00'}',
                  ),
                ),
                const Divider(height: 1),
                ListTile(
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => AddressListScreen(
                        state: state,
                      ),
                    ),
                  ),
                  leading: const Icon(
                    Icons.location_on_outlined,
                  ),
                  title: const Text('Addresses'),
                  trailing: const Icon(
                    Icons.chevron_right,
                  ),
                ),
                const Divider(height: 1),
                const ListTile(
                  leading: Icon(Icons.favorite_border),
                  title: Text('Wishlists'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          OutlinedButton.icon(
            onPressed: state.logout,
            icon: const Icon(Icons.logout),
            label: const Text('Sign out'),
          ),
        ],
      ),
    );
  }
}
