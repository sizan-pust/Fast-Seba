import 'package:flutter/material.dart';

import '../core/app_state.dart';

class AddressFormScreen extends StatefulWidget {
  const AddressFormScreen({
    super.key,
    required this.state,
  });

  final AppState state;

  @override
  State<AddressFormScreen> createState() => _AddressFormScreenState();
}

class _AddressFormScreenState extends State<AddressFormScreen> {
  final line1 = TextEditingController();
  final city = TextEditingController(
    text: 'Dhaka',
  );
  final mobile = TextEditingController();
  final landmark = TextEditingController();

  bool busy = false;
  String? error;

  @override
  void dispose() {
    line1.dispose();
    city.dispose();
    mobile.dispose();
    landmark.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    if (line1.text.trim().isEmpty || mobile.text.trim().isEmpty) {
      setState(() {
        error = 'Address and contact mobile are required.';
      });
      return;
    }

    setState(() {
      busy = true;
      error = null;
    });

    try {
      final address = await widget.state.createAddress({
        'address_line1': line1.text.trim(),
        'city': city.text.trim(),
        'landmark': landmark.text.trim(),
        'mobile': mobile.text.trim(),
        'address_type': 'home',
        'country': 'Bangladesh',
        'country_code': '+880',
        'latitude': 23.8103,
        'longitude': 90.4125,
        'is_default': true,
      });

      if (mounted) {
        Navigator.of(context).pop(address);
      }
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
    return Scaffold(
      appBar: AppBar(
        title: const Text('Add address'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          TextField(
            controller: line1,
            decoration: const InputDecoration(
              labelText: 'Address line',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: landmark,
            decoration: const InputDecoration(
              labelText: 'Landmark',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: city,
            decoration: const InputDecoration(
              labelText: 'City',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: mobile,
            keyboardType: TextInputType.phone,
            decoration: const InputDecoration(
              labelText: 'Contact mobile',
              border: OutlineInputBorder(),
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
            onPressed: busy ? null : submit,
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: busy
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                      ),
                    )
                  : const Text('Save address'),
            ),
          ),
        ],
      ),
    );
  }
}
