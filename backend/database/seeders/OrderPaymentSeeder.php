<?php
namespace Database\Seeders;
use App\Enums\GuardNameEnum;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
class OrderPaymentSeeder extends Seeder
{
    public function run(): void
    {
        Setting::query()->updateOrCreate(['variable'=>'payment'],['value'=>[
            'cod'=>true,'wallet'=>true,'offline'=>false,'razorpayPayment'=>false,
            'stripePayment'=>false,'paystackPayment'=>false,'flutterwavePayment'=>false,
            'currencyCode'=>'BDT','currencySymbol'=>'৳',
        ]]);
        foreach(['orders.view','orders.manage','payments.view','wallet.view'] as $name){
            Permission::findOrCreate($name,GuardNameEnum::ADMIN->value);
        }
        Role::query()->where('name','Super Admin')->where('guard_name',GuardNameEnum::ADMIN->value)->first()?->givePermissionTo(['orders.view','orders.manage','payments.view','wallet.view']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
