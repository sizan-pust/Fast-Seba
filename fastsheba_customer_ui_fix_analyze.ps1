param(
    [string]$ProjectPath = "D:\Workspace\fastsheba-platform\apps\customer"
)

$ErrorActionPreference = "Stop"

function Write-Utf8NoBom {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path,

        [Parameter(Mandatory = $true)]
        [string]$Content
    )

    $directory = Split-Path -Parent $Path

    if (-not [string]::IsNullOrWhiteSpace($directory)) {
        New-Item -ItemType Directory -Force -Path $directory | Out-Null
    }

    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $Content, $utf8NoBom)
}

$pubspecPath = Join-Path $ProjectPath "pubspec.yaml"
$ordersPath = Join-Path $ProjectPath "lib\src\screens\orders_screen.dart"
$testPath = Join-Path $ProjectPath "test\widget_test.dart"

if (-not (Test-Path $pubspecPath)) {
    throw "Flutter project not found: $ProjectPath"
}

if (-not (Get-Command flutter -ErrorAction SilentlyContinue)) {
    throw "Flutter is not available in PATH."
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$backupRoot = Join-Path $env:LOCALAPPDATA "FastShebaBackups\customer-analyze-fix\$timestamp"

New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null

foreach ($file in @($pubspecPath, $ordersPath, $testPath)) {
    if (Test-Path $file) {
        $relative = $file.Substring($ProjectPath.Length).TrimStart('\')
        $destination = Join-Path $backupRoot $relative
        $destinationDirectory = Split-Path -Parent $destination

        New-Item -ItemType Directory -Force -Path $destinationDirectory | Out-Null
        Copy-Item -Path $file -Destination $destination -Force
    }
}

Write-Utf8NoBom -Path $ordersPath -Content @'
import 'package:flutter/material.dart';

import '../core/app_state.dart';

class OrdersScreen extends StatelessWidget {
  const OrdersScreen({
    super.key,
    required this.state,
  });

  final AppState state;

  @override
  Widget build(BuildContext context) {
    if (!state.signedIn) {
      return const SafeArea(
        child: Center(
          child: Text('Sign in to view your orders.'),
        ),
      );
    }

    return SafeArea(
      child: RefreshIndicator(
        onRefresh: state.loadOrders,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            const Text(
              'My orders',
              style: TextStyle(
                fontSize: 28,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 18),
            if (state.orders.isEmpty)
              const Padding(
                padding: EdgeInsets.only(top: 100),
                child: Center(
                  child: Text('No orders yet.'),
                ),
              ),
            ...state.orders.map(
              (order) => Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Card(
                  child: ListTile(
                    contentPadding: const EdgeInsets.all(16),
                    leading: const CircleAvatar(
                      child: Icon(Icons.receipt_long),
                    ),
                    title: Text(
                      order['slug']?.toString() ?? '',
                    ),
                    subtitle: Text(
                      order['status_label']?.toString() ??
                          order['status']?.toString() ??
                          '',
                    ),
                    trailing: Text(
                      '৳${order['final_total'] ?? '0.00'}',
                      style: const TextStyle(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
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
'@

Write-Utf8NoBom -Path $testPath -Content @'
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
        ApiClient(baseUrl: 'http://127.0.0.1'),
      );

      await tester.pumpWidget(
        MaterialApp(
          home: OrdersScreen(state: state),
        ),
      );

      expect(
        find.text('Sign in to view your orders.'),
        findsOneWidget,
      );
    },
  );
}
'@

Push-Location $ProjectPath

try {
    & flutter pub add --dev flutter_lints

    if ($LASTEXITCODE -ne 0) {
        throw "Could not add flutter_lints."
    }

    & dart format `
        "lib\src\screens\orders_screen.dart" `
        "test\widget_test.dart"

    if ($LASTEXITCODE -ne 0) {
        throw "dart format failed."
    }

    & flutter pub get

    if ($LASTEXITCODE -ne 0) {
        throw "flutter pub get failed."
    }

    & flutter analyze

    if ($LASTEXITCODE -ne 0) {
        throw "flutter analyze still reports issues."
    }

    & flutter test

    if ($LASTEXITCODE -ne 0) {
        throw "flutter test failed."
    }
}
finally {
    Pop-Location
}

Write-Host ""
Write-Host "Customer Flutter analysis fix completed." -ForegroundColor Green
Write-Host "Backup: $backupRoot" -ForegroundColor Cyan
