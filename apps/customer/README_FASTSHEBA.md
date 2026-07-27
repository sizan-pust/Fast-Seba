# FastSheba Customer UI Foundation

This starter is original FastSheba code. It follows the same broad customer flow as the HyperLocal reference: splash, home categories, nearby products, product detail, cart, orders and account.

## Local run

Android emulator:

```powershell
flutter run --dart-define=API_BASE_URL=http://10.0.2.2
```

Physical Android device: use your computer's LAN IP and expose the Laravel site through a reachable host/port.

```powershell
flutter run --dart-define=API_BASE_URL=http://192.168.0.100:8000
```

The code intentionally uses only Flutter SDK APIs in this first foundation, so dependency risk stays low. Secure token storage, Firebase, maps, image loading, checkout forms and production routing are added in later UI batches.
