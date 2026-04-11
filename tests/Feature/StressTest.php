<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Collection;
use App\Models\CollectionCategory;
use App\Models\Fabric;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\GarmentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StressTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $tailor;
    protected User $secretary;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        foreach (['Admin', 'Manager', 'Tailor', 'Secretary', 'Cashier'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        $this->admin = User::factory()->create(['name' => 'Admin', 'email' => 'admin@test.com']);
        $this->admin->assignRole('Admin');

        $this->manager = User::factory()->create(['name' => 'Manager', 'email' => 'manager@test.com']);
        $this->manager->assignRole('Manager');

        $this->tailor = User::factory()->create(['name' => 'Tailor', 'email' => 'tailor@test.com']);
        $this->tailor->assignRole('Tailor');

        $this->secretary = User::factory()->create(['name' => 'Secretary', 'email' => 'secretary@test.com']);
        $this->secretary->assignRole('Secretary');
    }

    // ─── PUBLIC ROUTES ────────────────────────────────────────────

    public function test_shop_index_loads(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_login_page_loads(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_shop_checkout_requires_post(): void
    {
        $response = $this->get('/shop/checkout');
        $response->assertStatus(405);
    }

    // ─── AUTH GUARD ───────────────────────────────────────────────

    public function test_dashboard_requires_auth(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_clients_requires_auth(): void
    {
        $this->get('/clients')->assertRedirect('/login');
    }

    public function test_invoices_requires_auth(): void
    {
        $this->get('/invoices')->assertRedirect('/login');
    }

    public function test_collections_requires_auth(): void
    {
        $this->get('/collections')->assertRedirect('/login');
    }

    public function test_fabrics_requires_auth(): void
    {
        $this->get('/fabrics')->assertRedirect('/login');
    }

    public function test_expenses_requires_auth(): void
    {
        $this->get('/expenses')->assertRedirect('/login');
    }

    public function test_settings_requires_auth(): void
    {
        $this->get('/settings')->assertRedirect('/login');
    }

    public function test_reports_requires_auth(): void
    {
        $this->get('/reports')->assertRedirect('/login');
    }

    public function test_pos_requires_auth(): void
    {
        $this->get('/pos')->assertRedirect('/login');
    }

    public function test_inventory_requires_auth(): void
    {
        $this->get('/inventory')->assertRedirect('/login');
    }

    // ─── DASHBOARD ────────────────────────────────────────────────

    public function test_dashboard_loads_for_all_roles(): void
    {
        foreach ([$this->admin, $this->manager, $this->tailor, $this->secretary] as $user) {
            $this->actingAs($user)->get('/dashboard')->assertStatus(200);
        }
    }

    // ─── CLIENTS CRUD ─────────────────────────────────────────────

    public function test_clients_index_loads(): void
    {
        $this->actingAs($this->admin)->get('/clients')->assertStatus(200);
    }

    public function test_create_client(): void
    {
        $response = $this->actingAs($this->admin)->post('/clients', [
            'name' => 'Test Client',
            'phone' => '0712345678',
            'email' => 'client@test.com',
            'address' => '123 Test St',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('clients', ['name' => 'Test Client']);
    }

    public function test_show_client(): void
    {
        $client = Client::factory()->create();
        $this->actingAs($this->admin)->get("/clients/{$client->id}")->assertStatus(200);
    }

    public function test_update_client(): void
    {
        $client = Client::factory()->create(['name' => 'Old Name']);
        $response = $this->actingAs($this->admin)->put("/clients/{$client->id}", [
            'name' => 'Updated Name',
            'phone' => '0712345678',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Updated Name']);
    }

    public function test_delete_client(): void
    {
        $client = Client::factory()->create();
        $this->actingAs($this->admin)->delete("/clients/{$client->id}")->assertRedirect();
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    // ─── CONTACTS CRUD ────────────────────────────────────────────

    public function test_create_contact(): void
    {
        $client = Client::factory()->create();
        $response = $this->actingAs($this->admin)->post("/clients/{$client->id}/contacts", [
            'name' => 'Test Contact',
            'phone' => '0712345679',
            'relationship' => 'self',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('contacts', ['name' => 'Test Contact', 'client_id' => $client->id]);
    }

    public function test_show_contact(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $this->actingAs($this->admin)->get("/contacts/{$contact->id}")->assertStatus(200);
    }

    // ─── FABRICS CRUD ─────────────────────────────────────────────

    public function test_fabrics_index_loads(): void
    {
        $this->actingAs($this->admin)->get('/fabrics')->assertStatus(200);
    }

    public function test_create_fabric(): void
    {
        $response = $this->actingAs($this->admin)->post('/fabrics', [
            'name' => 'Test Silk',
            'type' => 'Silk',
            'color' => 'White',
            'price_per_unit' => 500,
            'stock_qty' => 100,
            'status' => 'active',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('fabrics', ['name' => 'Test Silk']);
    }

    public function test_update_fabric(): void
    {
        $fabric = Fabric::factory()->create(['name' => 'Old Fabric']);
        $response = $this->actingAs($this->admin)->put("/fabrics/{$fabric->id}", [
            'name' => 'Updated Fabric',
            'type' => 'Cotton',
            'color' => 'Blue',
            'price_per_unit' => 600,
            'stock_qty' => 50,
            'status' => 'active',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('fabrics', ['id' => $fabric->id, 'name' => 'Updated Fabric']);
    }

    public function test_delete_fabric(): void
    {
        $fabric = Fabric::factory()->create();
        $this->actingAs($this->admin)->delete("/fabrics/{$fabric->id}")->assertRedirect();
        $this->assertDatabaseMissing('fabrics', ['id' => $fabric->id]);
    }

    // ─── EXPENSES CRUD ────────────────────────────────────────────

    public function test_expenses_index_loads(): void
    {
        $this->actingAs($this->admin)->get('/expenses')->assertStatus(200);
    }

    public function test_create_expense(): void
    {
        $response = $this->actingAs($this->admin)->post('/expenses', [
            'description' => 'Thread purchase',
            'amount' => 1500,
            'category' => 'materials',
            'date' => now()->format('Y-m-d'),
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('expenses', ['description' => 'Thread purchase']);
    }

    // ─── COLLECTIONS CRUD ─────────────────────────────────────────

    public function test_collections_index_loads(): void
    {
        $this->actingAs($this->admin)->get('/collections')->assertStatus(200);
    }

    public function test_create_collection_item(): void
    {
        $response = $this->actingAs($this->admin)->post('/collections', [
            'name' => 'Ready-made Kanzu',
            'price' => 3500,
            'stock_qty' => 10,
            'status' => 'active',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('collections', ['name' => 'Ready-made Kanzu']);
    }

    public function test_update_collection_item(): void
    {
        $item = Collection::factory()->create(['name' => 'Old Item']);
        $response = $this->actingAs($this->admin)->put("/collections/{$item->id}", [
            'name' => 'Updated Item',
            'price' => 4000,
            'stock_qty' => 15,
            'status' => 'active',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('collections', ['id' => $item->id, 'name' => 'Updated Item']);
    }

    public function test_delete_collection_item(): void
    {
        $item = Collection::factory()->create();
        $this->actingAs($this->admin)->delete("/collections/{$item->id}")->assertRedirect();
        $this->assertDatabaseMissing('collections', ['id' => $item->id]);
    }

    public function test_collection_stock_adjust(): void
    {
        $item = Collection::factory()->create(['stock_qty' => 10]);
        $response = $this->actingAs($this->admin)->post("/collections/{$item->id}/adjust-stock", [
            'adjustment' => 5,
            'reason' => 'New shipment',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('collections', ['id' => $item->id, 'stock_qty' => 15]);
    }

    // ─── COLLECTION CATEGORIES ────────────────────────────────────

    public function test_create_collection_category(): void
    {
        $response = $this->actingAs($this->admin)->post('/collection-categories', [
            'name' => 'Kanzus',
            'description' => 'Ready-made kanzus',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('collection_categories', ['name' => 'Kanzus']);
    }

    public function test_update_collection_category(): void
    {
        $cat = CollectionCategory::factory()->create(['name' => 'Old Cat']);
        $response = $this->actingAs($this->admin)->put("/collection-categories/{$cat->id}", [
            'name' => 'Updated Cat',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('collection_categories', ['id' => $cat->id, 'name' => 'Updated Cat']);
    }

    // ─── INVOICES ─────────────────────────────────────────────────

    public function test_invoices_index_loads(): void
    {
        $this->actingAs($this->admin)->get('/invoices')->assertStatus(200);
    }

    public function test_invoices_create_page_loads(): void
    {
        $this->actingAs($this->admin)->get('/invoices/create')->assertStatus(200);
    }

    // ─── SETTINGS ─────────────────────────────────────────────────

    public function test_settings_loads_for_admin(): void
    {
        $this->actingAs($this->admin)->get('/settings')->assertStatus(200);
    }

    // ─── REPORTS ──────────────────────────────────────────────────

    public function test_reports_loads(): void
    {
        $this->actingAs($this->admin)->get('/reports')->assertStatus(200);
    }

    // ─── INVENTORY ────────────────────────────────────────────────

    public function test_inventory_loads(): void
    {
        $this->actingAs($this->admin)->get('/inventory')->assertStatus(200);
    }

    // ─── POS ──────────────────────────────────────────────────────

    public function test_pos_loads(): void
    {
        $this->actingAs($this->admin)->get('/pos')->assertStatus(200);
    }

    // ─── GARMENT TYPES ────────────────────────────────────────────

    public function test_create_garment_type(): void
    {
        $response = $this->actingAs($this->admin)->post('/settings/garment-types', [
            'name' => 'Test Kanzu',
            'color' => '#22c55e',
            'base_price' => 2500,
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('garment_types', ['name' => 'Test Kanzu']);
    }

    public function test_garment_type_empty_color_no_crash(): void
    {
        $gt = GarmentType::factory()->create(['color' => '#22c55e']);
        $gt->color = '';
        $classes = $gt->tailwind_classes;
        $this->assertIsArray($classes);
        $this->assertArrayHasKey('bg', $classes);
    }

    // ─── CONCURRENT LOAD SIMULATION ───────────────────────────────

    public function test_rapid_page_loads(): void
    {
        $pages = [
            '/dashboard', '/clients', '/fabrics', '/expenses',
            '/collections', '/invoices', '/settings', '/reports',
            '/inventory', '/pos', '/invoices/create',
        ];

        foreach ($pages as $page) {
            $response = $this->actingAs($this->admin)->get($page);
            $this->assertTrue(
                in_array($response->status(), [200, 302]),
                "Page {$page} returned status {$response->status()}"
            );
        }
    }

    // ─── EDGE CASES ───────────────────────────────────────────────

    public function test_nonexistent_client_returns_404(): void
    {
        $this->actingAs($this->admin)->get('/clients/99999')->assertStatus(404);
    }

    public function test_nonexistent_invoice_returns_404(): void
    {
        $this->actingAs($this->admin)->get('/invoices/99999')->assertStatus(404);
    }

    public function test_put_to_collections_index_returns_405(): void
    {
        $response = $this->actingAs($this->admin)->put('/collections', ['name' => 'Test']);
        $response->assertStatus(405);
    }

    public function test_invalid_client_data_returns_validation_error(): void
    {
        $response = $this->actingAs($this->admin)->post('/clients', []);
        $response->assertSessionHasErrors();
    }

    public function test_client_statement_loads(): void
    {
        $client = Client::factory()->create();
        $this->actingAs($this->admin)->get("/clients/{$client->id}/statement")->assertStatus(200);
    }

    public function test_client_statement_pdf(): void
    {
        $client = Client::factory()->create();
        $response = $this->actingAs($this->admin)->get("/clients/{$client->id}/statement/pdf");
        $this->assertTrue(in_array($response->status(), [200, 302]));
    }
}
