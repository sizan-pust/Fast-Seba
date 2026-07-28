import 'package:flutter/material.dart';
import 'core/api_client.dart';
import 'core/app_state.dart';
import 'screens/root_screen.dart';

class FastShebaApp extends StatefulWidget {
  const FastShebaApp({super.key});
  @override
  State<FastShebaApp> createState() => _FastShebaAppState();
}

class _FastShebaAppState extends State<FastShebaApp> {
  late final AppState state;
  @override
  void initState() {
    super.initState();
    state = AppState(ApiClient());
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'FastSheba',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF137A4B),
          brightness: Brightness.light,
        ),
        useMaterial3: true,
        scaffoldBackgroundColor: const Color(0xFFF7F9F8),
        cardTheme: const CardThemeData(
          elevation: 0,
          margin: EdgeInsets.zero,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.all(Radius.circular(18)),
          ),
        ),
      ),
      home: RootScreen(state: state),
    );
  }
}
