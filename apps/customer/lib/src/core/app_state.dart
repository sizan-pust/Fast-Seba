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
  List<Map<String, dynamic>> addresses = [];
  List<Map<String, dynamic>> promos = [];
  Map<String, dynamic>? wallet;
  Map<String, dynamic>? selectedOrder;

  bool get signedIn => api.isAuthenticated;

  Future<void> bootstrap() async {
    await loadHome();
  }

  Future<void> loadHome() async {
    await _guard(() async {
      final categoryResponse = await api.get(
        '/categories',
        query: {
          'home': 'true',
          'latitude': '23.8103',
          'longitude': '90.4125',
        },
      );

      final productResponse = await api.get(
        '/delivery-zone/products',
        query: {
          'latitude': '23.8103',
          'longitude': '90.4125',
        },
      );

      categories = _paginatedItems(
        categoryResponse,
      );

      products = _paginatedItems(
        productResponse,
      );
    });
  }

  Future<void> login(
    String email,
    String password,
  ) async {
    await _guard(() async {
      final response = await api.post(
        '/login',
        body: {
          'email': email,
          'password': password,
          'device_type': 'android',
        },
      );

      api.setToken(
        response['access_token']?.toString(),
      );

      user = response['data'] is Map<String, dynamic>
          ? Map<String, dynamic>.from(
              response['data'] as Map,
            )
          : null;

      await loadCart();
      await loadOrders();
      await loadAddresses();
      await loadWallet();
      await loadPromos();
    });
  }

  Future<void> logout() async {
    try {
      if (signedIn) {
        await api.post(
          '/logout',
          body: {},
        );
      }
    } finally {
      api.setToken(null);
      user = null;
      cart = null;
      orders = [];
      addresses = [];
      promos = [];
      wallet = null;
      selectedOrder = null;
      notifyListeners();
    }
  }

  Future<void> addToCart(
    Map<String, dynamic> product,
  ) async {
    if (!signedIn) {
      throw ApiException(
        'Please sign in first.',
      );
    }

    final variants = product['variants'];

    if (variants is! List || variants.isEmpty) {
      throw ApiException(
        'No purchasable variant is available.',
      );
    }

    final variant = Map<String, dynamic>.from(
      variants.first as Map,
    );

    await _guard(() async {
      await api.post(
        '/user/cart/add',
        body: {
          'product_variant_id': variant['id'],
          'store_id': variant['store_id'],
          'quantity': 1,
        },
      );

      await loadCart();
    });
  }

  Future<void> loadCart({
    int? addressId,
    String? promoCode,
    bool useWallet = false,
  }) async {
    if (!signedIn) {
      return;
    }

    final query = <String, String>{
      'latitude': '23.8103',
      'longitude': '90.4125',
      'use_wallet': useWallet.toString(),
      'delivery_type': 'delivery',
    };

    if (addressId != null) {
      query['address_id'] = addressId.toString();
    }

    if (promoCode != null && promoCode.trim().isNotEmpty) {
      query['promo_code'] = promoCode.trim();
    }

    final response = await api.get(
      '/user/cart',
      query: query,
    );

    cart = response['data'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(
            response['data'] as Map,
          )
        : null;

    notifyListeners();
  }

  Future<void> loadOrders() async {
    if (!signedIn) {
      return;
    }

    final response = await api.get(
      '/user/orders',
    );

    orders = _paginatedItems(response);
    notifyListeners();
  }

  Future<Map<String, dynamic>> loadOrder(
    String slug,
  ) async {
    final response = await api.get(
      '/user/orders/$slug',
    );

    selectedOrder = response['data'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(
            response['data'] as Map,
          )
        : null;

    notifyListeners();

    return selectedOrder ?? <String, dynamic>{};
  }

  Future<Map<String, dynamic>> loadDeliveryLocation(
    String slug,
  ) async {
    final response = await api.get(
      '/user/orders/$slug/'
      'delivery-boy-location',
    );

    return response['data'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(
            response['data'] as Map,
          )
        : <String, dynamic>{};
  }

  Future<void> loadAddresses() async {
    if (!signedIn) {
      return;
    }

    final response = await api.get(
      '/user/addresses',
    );

    addresses = _plainItems(response);
    notifyListeners();
  }

  Future<Map<String, dynamic>> createAddress(
    Map<String, dynamic> body,
  ) async {
    final response = await api.post(
      '/user/addresses',
      body: body,
    );

    await loadAddresses();

    return response['data'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(
            response['data'] as Map,
          )
        : <String, dynamic>{};
  }

  Future<void> loadWallet() async {
    if (!signedIn) {
      return;
    }

    final response = await api.get(
      '/user/wallet',
    );

    wallet = response['data'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(
            response['data'] as Map,
          )
        : null;

    notifyListeners();
  }

  Future<void> loadPromos() async {
    if (!signedIn) {
      return;
    }

    final response = await api.get(
      '/user/promos/available',
    );

    promos = _plainItems(response);
    notifyListeners();
  }

  Future<Map<String, dynamic>> placeOrder({
    required int addressId,
    required String paymentType,
    String? promoCode,
    bool useWallet = false,
    String? note,
  }) async {
    final response = await api.post(
      '/user/orders',
      body: {
        'payment_type': paymentType,
        'address_id': addressId,
        'delivery_type': 'delivery',
        'promo_code': (promoCode == null || promoCode.trim().isEmpty)
            ? null
            : promoCode.trim(),
        'use_wallet': useWallet,
        'rush_delivery': false,
        'order_note': note,
      },
    );

    final order = response['data'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(
            response['data'] as Map,
          )
        : <String, dynamic>{};

    await loadCart();
    await loadOrders();
    await loadWallet();

    selectedOrder = order;
    notifyListeners();

    return order;
  }

  Future<Map<String, dynamic>> requestReturn({
    required int orderItemId,
    required String reason,
  }) async {
    final response = await api.post(
      '/user/orders/items/'
      '$orderItemId/return',
      body: {
        'reason': reason,
        'refund_method': 'wallet',
      },
    );

    return response['data'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(
            response['data'] as Map,
          )
        : <String, dynamic>{};
  }

  List<Map<String, dynamic>> _paginatedItems(
    Map<String, dynamic> response,
  ) {
    final outer = response['data'];

    if (outer is Map && outer['data'] is List) {
      return (outer['data'] as List)
          .whereType<Map>()
          .map(
            (item) => Map<String, dynamic>.from(
              item,
            ),
          )
          .toList();
    }

    if (outer is List) {
      return outer
          .whereType<Map>()
          .map(
            (item) => Map<String, dynamic>.from(
              item,
            ),
          )
          .toList();
    }

    return [];
  }

  List<Map<String, dynamic>> _plainItems(
    Map<String, dynamic> response,
  ) {
    final data = response['data'];

    if (data is List) {
      return data
          .whereType<Map>()
          .map(
            (item) => Map<String, dynamic>.from(
              item,
            ),
          )
          .toList();
    }

    if (data is Map && data['data'] is List) {
      return (data['data'] as List)
          .whereType<Map>()
          .map(
            (item) => Map<String, dynamic>.from(
              item,
            ),
          )
          .toList();
    }

    return [];
  }

  Future<void> _guard(
    Future<void> Function() action,
  ) async {
    loading = true;
    error = null;
    notifyListeners();

    try {
      await action();
    } catch (exception) {
      error = exception.toString();
      rethrow;
    } finally {
      loading = false;
      notifyListeners();
    }
  }
}
