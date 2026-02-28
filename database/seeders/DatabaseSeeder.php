<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Fabric;
use App\Models\Measurement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $roles = ['Admin', 'Manager', 'Tailor', 'Secretary'];
        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        // Create users
        $admin = User::firstOrCreate(
            ['email' => 'admin@libas.test'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('password'),
                'phone' => '+254700000000',
                'status' => 'active',
            ]
        );
        $admin->assignRole('Admin');

        $manager = User::firstOrCreate(
            ['email' => 'manager@libas.test'],
            [
                'name' => 'Manager User',
                'password' => bcrypt('password'),
                'phone' => '+254711111111',
                'status' => 'active',
            ]
        );
        $manager->assignRole('Manager');

        $tailor = User::firstOrCreate(
            ['email' => 'tailor@libas.test'],
            [
                'name' => 'Hassan Tailor',
                'password' => bcrypt('password'),
                'phone' => '+254722222222',
                'status' => 'active',
            ]
        );
        $tailor->assignRole('Tailor');

        // Sample fabrics
        $fabrics = [
            ['name' => 'White Cotton Poplin', 'type' => 'Cotton', 'color' => '#FFFFFF', 'price_per_unit' => 850, 'stock_qty' => 50, 'supplier' => 'Mombasa Textiles'],
            ['name' => 'Cream Linen', 'type' => 'Linen', 'color' => '#FFFDD0', 'price_per_unit' => 1200, 'stock_qty' => 30, 'supplier' => 'Mombasa Textiles'],
            ['name' => 'Light Blue Cotton', 'type' => 'Cotton', 'color' => '#ADD8E6', 'price_per_unit' => 900, 'stock_qty' => 40, 'supplier' => 'Nairobi Fabrics'],
            ['name' => 'Black Wool Blend', 'type' => 'Wool', 'color' => '#000000', 'price_per_unit' => 1800, 'stock_qty' => 20, 'supplier' => 'Premium Imports'],
            ['name' => 'Ivory Silk', 'type' => 'Silk', 'color' => '#FFFFF0', 'price_per_unit' => 2500, 'stock_qty' => 15, 'supplier' => 'Premium Imports'],
            ['name' => 'Grey Polyester', 'type' => 'Polyester', 'color' => '#808080', 'price_per_unit' => 650, 'stock_qty' => 60, 'supplier' => 'Nairobi Fabrics'],
            ['name' => 'Olive Green Cotton', 'type' => 'Cotton', 'color' => '#808000', 'price_per_unit' => 950, 'stock_qty' => 25, 'supplier' => 'Mombasa Textiles'],
            ['name' => 'Navy Blue Linen', 'type' => 'Linen', 'color' => '#000080', 'price_per_unit' => 1300, 'stock_qty' => 18, 'supplier' => 'Premium Imports'],
        ];
        foreach ($fabrics as $fabric) {
            Fabric::firstOrCreate(['name' => $fabric['name']], $fabric);
        }

        // Sample client with contacts and measurements
        $client = Client::firstOrCreate(
            ['phone' => '+254733333333'],
            [
                'name' => 'Ali Hassan',
                'email' => 'ali.hassan@example.com',
                'type' => 'family',
                'status' => 'active',
            ]
        );

        $contactAli = Contact::firstOrCreate(
            ['client_id' => $client->id, 'name' => 'Ali Hassan'],
            [
                'relationship' => 'self',
                'gender' => 'male',
                'age_group' => 'adult',
            ]
        );

        Measurement::firstOrCreate(
            ['contact_id' => $contactAli->id, 'label' => '2026 Eid Kanzu'],
            [
                'garment_type' => 'kanzu',
                'date_taken' => '2026-02-20',
                'unit' => 'cm',
                'values' => [
                    'length' => 150,
                    'chest' => 104,
                    'shoulder' => 48,
                    'sleeve' => 62,
                    'neck' => 40,
                    'cross_back' => 42,
                ],
                'measured_by' => $tailor->id,
            ]
        );

        $contactMusa = Contact::firstOrCreate(
            ['client_id' => $client->id, 'name' => 'Musa Hassan'],
            [
                'relationship' => 'son',
                'gender' => 'male',
                'age_group' => 'teen',
            ]
        );

        Measurement::firstOrCreate(
            ['contact_id' => $contactMusa->id, 'label' => '2026 School Shirt'],
            [
                'garment_type' => 'shirt',
                'date_taken' => '2026-02-20',
                'unit' => 'cm',
                'values' => [
                    'neck' => 34,
                    'chest' => 82,
                    'waist' => 70,
                    'sleeve_length' => 54,
                    'shirt_length' => 68,
                    'sleeve_type' => 'cuff',
                ],
                'measured_by' => $tailor->id,
            ]
        );

        // Second client
        $client2 = Client::firstOrCreate(
            ['phone' => '+254744444444'],
            [
                'name' => 'Omar Sheikh',
                'type' => 'individual',
                'status' => 'active',
            ]
        );

        $contactOmar = Contact::firstOrCreate(
            ['client_id' => $client2->id, 'name' => 'Omar Sheikh'],
            [
                'relationship' => 'self',
                'gender' => 'male',
                'age_group' => 'adult',
            ]
        );

        Measurement::firstOrCreate(
            ['contact_id' => $contactOmar->id, 'label' => '2026 Kanzu'],
            [
                'garment_type' => 'kanzu',
                'date_taken' => '2026-02-22',
                'unit' => 'cm',
                'values' => [
                    'length' => 145,
                    'chest' => 100,
                    'shoulder' => 46,
                    'sleeve' => 60,
                    'neck' => 39,
                ],
                'measured_by' => $tailor->id,
            ]
        );
    }
}
