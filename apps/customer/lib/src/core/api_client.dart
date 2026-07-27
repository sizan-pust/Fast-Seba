import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.errors});
  final String message;
  final int? statusCode;
  final Object? errors;
  @override
  String toString() => message;
}

class ApiClient extends ChangeNotifier {
  ApiClient({String? baseUrl})
      : baseUrl = baseUrl ?? const String.fromEnvironment(
          'API_BASE_URL',
          defaultValue: 'http://10.0.2.2',
        );

  final String baseUrl;
  String? _token;
  String? get token => _token;
  bool get isAuthenticated => _token != null && _token!.isNotEmpty;

  void setToken(String? value) {
    _token = value;
    notifyListeners();
  }

  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, String>? query,
  }) => _request('GET', path, query: query);

  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, dynamic>? body,
  }) => _request('POST', path, body: body);

  Future<Map<String, dynamic>> delete(
    String path, {
    Map<String, dynamic>? body,
  }) => _request('DELETE', path, body: body);

  Future<Map<String, dynamic>> _request(
    String method,
    String path, {
    Map<String, String>? query,
    Map<String, dynamic>? body,
  }) async {
    final normalizedBase = baseUrl.endsWith('/')
        ? baseUrl.substring(0, baseUrl.length - 1)
        : baseUrl;
    final normalizedPath = path.startsWith('/') ? path : '/$path';
    var uri = Uri.parse('$normalizedBase/api$normalizedPath');
    if (query != null && query.isNotEmpty) {
      uri = uri.replace(queryParameters: query);
    }

    final client = HttpClient()..connectionTimeout = const Duration(seconds: 15);
    try {
      final request = await client.openUrl(method, uri);
      request.headers.set(HttpHeaders.acceptHeader, 'application/json');
      request.headers.set(HttpHeaders.contentTypeHeader, 'application/json');
      if (_token != null) {
        request.headers.set(HttpHeaders.authorizationHeader, 'Bearer $_token');
      }
      if (body != null) {
        request.write(jsonEncode(body));
      }
      final response = await request.close();
      final text = await utf8.decoder.bind(response).join();
      final decoded = text.isEmpty ? <String, dynamic>{} : jsonDecode(text);
      final map = decoded is Map<String, dynamic>
          ? decoded
          : <String, dynamic>{'data': decoded};
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw ApiException(
          map['message']?.toString() ?? 'Request failed.',
          statusCode: response.statusCode,
          errors: map['errors'] ?? map['data'],
        );
      }
      return map;
    } finally {
      client.close(force: true);
    }
  }
}
