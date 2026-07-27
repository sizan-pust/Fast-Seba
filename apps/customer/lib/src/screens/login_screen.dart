import 'package:flutter/material.dart';
import '../core/app_state.dart';
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key,required this.state});final AppState state;
  @override State<LoginScreen> createState()=>_LoginScreenState();
}
class _LoginScreenState extends State<LoginScreen>{
  final email=TextEditingController();final password=TextEditingController();bool busy=false;String? error;
  @override Widget build(BuildContext context)=>Scaffold(appBar:AppBar(),body:ListView(padding:const EdgeInsets.all(24),children:[
    const Icon(Icons.local_pharmacy_rounded,size:72,color:Color(0xFF137A4B)),const SizedBox(height:18),
    const Text('Welcome back',textAlign:TextAlign.center,style:TextStyle(fontSize:28,fontWeight:FontWeight.w900)),const SizedBox(height:8),
    const Text('Sign in to access cart, wallet and orders.',textAlign:TextAlign.center,style:TextStyle(color:Colors.black54)),const SizedBox(height:28),
    TextField(controller:email,keyboardType:TextInputType.emailAddress,decoration:const InputDecoration(labelText:'Email',border:OutlineInputBorder())),const SizedBox(height:14),
    TextField(controller:password,obscureText:true,decoration:const InputDecoration(labelText:'Password',border:OutlineInputBorder())),
    if(error!=null) Padding(padding:const EdgeInsets.only(top:12),child:Text(error!,style:const TextStyle(color:Colors.red))),const SizedBox(height:20),
    FilledButton(onPressed:busy?null:()async{setState((){busy=true;error=null;});try{await widget.state.login(email.text.trim(),password.text);if(context.mounted)Navigator.pop(context);}catch(e){setState(()=>error=e.toString());}finally{if(mounted)setState(()=>busy=false);}},child:Padding(padding:const EdgeInsets.all(14),child:busy?const SizedBox(width:22,height:22,child:CircularProgressIndicator(strokeWidth:2)):const Text('Sign in'))),
    const SizedBox(height:14),const Text('Local demo customer credentials can be used after creating a customer in the backend.',textAlign:TextAlign.center,style:TextStyle(fontSize:12,color:Colors.black54)),
  ]));
}
