import 'package:flutter/foundation.dart';
import 'api_client.dart';

class AppState extends ChangeNotifier {
  AppState(this.api);
  final ApiClient api;

  bool loading = false;
  String? error;
  Map<String, dynamic>? user;
  List<Map<String, dynamic>> categories = [];
  List<Map<String, dynamic>> products = [];
  Map<String, dynamic>? cart;
  List<Map<String, dynamic>> orders = [];

  bool get signedIn => api.isAuthenticated;

  Future<void> bootstrap() async {
    await loadHome();
  }

  Future<void> loadHome() async {
    await _guard(() async {
      final categoryResponse = await api.get('/categories', query: {
        'home': 'true',
        'latitude': '23.8103',
        'longitude': '90.4125',
      });
      final productResponse = await api.get('/delivery-zone/products', query: {
        'latitude': '23.8103',
        'longitude': '90.4125',
      });
      categories = _paginatedItems(categoryResponse);
      products = _paginatedItems(productResponse);
    });
  }

  Future<void> login(String email, String password) async {
    await _guard(() async {
      final response = await api.post('/login', body: {
        'email': email,
        'password': password,
        'device_type': 'android',
      });
      api.setToken(response['access_token']?.toString());
      user = response['data'] is Map<String, dynamic>
          ? response['data'] as Map<String, dynamic>
          : null;
      await loadCart();
    });
  }

  Future<void> logout() async {
    try {
      if (signedIn) await api.post('/logout', body: {});
    } finally {
      api.setToken(null);
      user = null;
      cart = null;
      orders = [];
      notifyListeners();
    }
  }

  Future<void> addToCart(Map<String, dynamic> product) async {
    if (!signedIn) throw ApiException('Please sign in first.');
    final variants = product['variants'];
    if (variants is! List || variants.isEmpty) {
      throw ApiException('No purchasable variant is available.');
    }
    final variant = Map<String, dynamic>.from(variants.first as Map);
    await _guard(() async {
      await api.post('/user/cart/add', body: {
        'product_variant_id': variant['id'],
        'store_id': variant['store_id'],
        'quantity': 1,
      });
      await loadCart();
    });
  }

  Future<void> loadCart() async {
    if (!signedIn) return;
    final response = await api.get('/user/cart', query: {
      'latitude': '23.8103',
      'longitude': '90.4125',
    });
    cart = response['data'] is Map<String, dynamic>
        ? response['data'] as Map<String, dynamic>
        : null;
    notifyListeners();
  }

  Future<void> loadOrders() async {
    if (!signedIn) return;
    await _guard(() async {
      final response = await api.get('/user/orders');
      orders = _paginatedItems(response);
    });
  }

  List<Map<String, dynamic>> _paginatedItems(Map<String, dynamic> response) {
    final outer = response['data'];
    if (outer is Map && outer['data'] is List) {
      return (outer['data'] as List)
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList();
    }
    if (outer is List) {
      return outer.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
    }
    return [];
  }

  Future<void> _guard(Future<void> Function() action) async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      await action();
    } catch (e) {
      error = e.toString();
      rethrow;
    } finally {
      loading = false;
      notifyListeners();
    }
  }
}
