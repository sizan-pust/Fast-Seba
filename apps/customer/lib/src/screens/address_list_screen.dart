import 'package:flutter/material.dart';

import '../core/app_state.dart';
import 'address_form_screen.dart';

class AddressListScreen extends StatefulWidget {
  const AddressListScreen({
    super.key,
    required this.state,
    this.selectMode = false,
  });

  final AppState state;
  final bool selectMode;

  @override
  State<AddressListScreen> createState() => _AddressListScreenState();
}

class _AddressListScreenState extends State<AddressListScreen> {
  @override
  void initState() {
    super.initState();
    widget.state.loadAddresses();
  }

  Future<void> addAddress() async {
    final address = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(
        builder: (_) => AddressFormScreen(
          state: widget.state,
        ),
      ),
    );

    if (address != null && widget.selectMode && mounted) {
      Navigator.of(context).pop(address);
      return;
    }

    if (mounted) {
      setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Addresses'),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: addAddress,
        icon: const Icon(
          Icons.add_location_alt_outlined,
        ),
        label: const Text('Add'),
      ),
      body: RefreshIndicator(
        onRefresh: widget.state.loadAddresses,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            if (widget.state.addresses.isEmpty)
              const Padding(
                padding: EdgeInsets.only(top: 120),
                child: Center(
                  child: Text(
                    'No address saved yet.',
                  ),
                ),
              ),
            ...widget.state.addresses.map(
              (address) => Padding(
                padding: const EdgeInsets.only(
                  bottom: 12,
                ),
                child: Card(
                  child: ListTile(
                    onTap: widget.selectMode
                        ? () => Navigator.of(context).pop(address)
                        : null,
                    leading: const CircleAvatar(
                      child: Icon(
                        Icons.location_on_outlined,
                      ),
                    ),
                    title: Text(
                      address['address_line1']?.toString() ?? '',
                    ),
                    subtitle: Text(
                      [
                        address['landmark'],
                        address['city'],
                        address['mobile'],
                      ]
                          .where(
                            (value) =>
                                value != null &&
                                value.toString().trim().isNotEmpty,
                          )
                          .join(' • '),
                    ),
                    trailing: address['is_default'] == true
                        ? const Chip(
                            label: Text('Default'),
                          )
                        : widget.selectMode
                            ? const Icon(
                                Icons.chevron_right,
                              )
                            : null,
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
