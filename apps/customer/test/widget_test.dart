import 'package:fastsheba_customer/src/core/api_client.dart';
import 'package:fastsheba_customer/src/core/app_state.dart';
import 'package:fastsheba_customer/src/screens/orders_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets(
    'orders screen asks unauthenticated users to sign in',
    (WidgetTester tester) async {
      final state = AppState(
        ApiClient(
          baseUrl: 'http://127.0.0.1',
        ),
      );

      await tester.pumpWidget(
        MaterialApp(
          home: OrdersScreen(
            state: state,
          ),
        ),
      );

      expect(
        find.text(
          'Sign in to view your orders.',
        ),
        findsOneWidget,
      );
    },
  );
}
