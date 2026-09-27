<?php

class Seeder
{
    private PDO $pdo;
    private $out;

    public function __construct(PDO $pdo, callable $out)
    {
        $this->pdo = $pdo;
        $this->out = $out;
    }

    public function run(): int
    {
        $count = 0;
        $count += $this->seedUsers();
        $count += $this->seedSettings();
        $count += $this->seedCategories();
        $count += $this->seedCertificates();
        $count += $this->seedTeam();
        $count += $this->seedTestimonials();
        $count += $this->seedProducts();
        return $count;
    }

    private function seedUsers(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM users");
        if ($stmt->fetchColumn() > 0) return 0;

        $now = date('Y-m-d H:i:s');
        $sql = "INSERT INTO users (public_id, name, email, password, role_id, landing_page, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'usr_admin001',
            'BCE Admin',
            'admin@bceexport.com',
            password_hash('admin123', PASSWORD_DEFAULT),
            'admin',
            'dashboard',
            'active',
            $now,
            $now
        ]);
        ($this->out)("  + Seeded Admin User (admin@bceexport.com / admin123)");
        return 1;
    }

    private function seedSettings(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM settings");
        if ($stmt->fetchColumn() > 0) return 0;

        $now = date('Y-m-d H:i:s');
        $settings = [
            'general' => [
                'site_name' => 'BCE Export',
                'tagline' => 'Excellence in Global Export & Indian Craftsmanship',
                'description' => 'Leading exporter of Indian Handicrafts, Leather, Jute, Dhokra, Terracotta, Furniture, and Produce.',
                'logo' => '/img/logo.webp',
            ],
            'contact' => [
                'phone' => '+91 8900379037',
                'whatsapp' => '+91 8900379037',
                'email' => 'info@bceexport.com',
                'address' => 'Kolkata, West Bengal, India',
                'working_hours' => 'Mon - Sat: 9:00 AM - 7:00 PM IST'
            ],
            'stats' => [
                'clients' => '700+',
                'exports' => '654+',
                'products' => '565+'
            ],
            'social' => [
                'facebook' => 'https://www.facebook.com/Bceexport',
                'instagram' => 'https://www.instagram.com/bceexport/',
                'linkedin' => 'https://linkedin.com/company/bceexport',
                'whatsapp' => 'https://wa.me/+918900379037'
            ]
        ];

        $stmt = $this->pdo->prepare("INSERT INTO settings (setting_group, setting_key, setting_value, created_at, updated_at) VALUES (?, ?, ?, ?, ?)");
        $count = 0;
        foreach ($settings as $group => $data) {
            foreach ($data as $key => $val) {
                $stmt->execute([$group, $key, json_encode($val), $now, $now]);
                $count++;
            }
        }
        ($this->out)("  + Seeded General, Contact, Stats, & Social Settings");
        return $count;
    }

    private function seedCategories(): int
    {
        $now = date('Y-m-d H:i:s');
        $categories = [
            ['slug' => 'leather', 'name' => 'Leather Goods', 'description' => 'Premium handcrafted genuine leather products & bags.', 'image' => '/img/Leather01.webp'],
            ['slug' => 'wooden-handicraft', 'name' => 'Wooden Handicraft', 'description' => 'Exquisite hand-carved wooden decorative items and sculptures.', 'image' => '/img/Wooden Crafts.webp'],
            ['slug' => 'furniture', 'name' => 'Furniture', 'description' => 'Royal handcrafted wooden, cane and designer furniture.', 'image' => '/img/Furniture_Main.webp'],
            ['slug' => 'jute', 'name' => 'Jute Products', 'description' => 'Eco-friendly sustainable jute bags, rugs & home decor.', 'image' => '/img/Handcrafted_jute.webp'],
            ['slug' => 'dhokra', 'name' => 'Dhokra Art', 'description' => 'Ancient non-ferrous lost-wax metal casting art form of Bengal.', 'image' => '/img/Dhokra Handcrafted.webp'],
            ['slug' => 'terracotta', 'name' => 'Terracotta', 'description' => 'Traditional clay pottery, statues, diyas & wall decor.', 'image' => '/img/Terracotta01.webp'],
            ['slug' => 'fruit-vegetable', 'name' => 'Fruit & Vegetable', 'description' => 'Fresh, organic export-grade agricultural produce from Indian farms.', 'image' => '/img/Fruit.webp'],
        ];

        $checkStmt = $this->pdo->prepare("SELECT id FROM categories WHERE slug = ?");
        $insertStmt = $this->pdo->prepare("INSERT INTO categories (slug, name, description, image, status, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $updateStmt = $this->pdo->prepare("UPDATE categories SET name = ?, description = ?, image = ?, status = ?, sort_order = ?, updated_at = ? WHERE slug = ?");

        $count = 0;
        $order = 1;
        foreach ($categories as $cat) {
            $checkStmt->execute([$cat['slug']]);
            if ($checkStmt->fetch()) {
                $updateStmt->execute([$cat['name'], $cat['description'], $cat['image'], 'published', $order++, $now, $cat['slug']]);
            } else {
                $insertStmt->execute([$cat['slug'], $cat['name'], $cat['description'], $cat['image'], 'published', $order++, $now, $now]);
                $count++;
            }
        }
        ($this->out)("  + Seeded/Updated 7 Product Categories");
        return count($categories);
    }

    private function seedCertificates(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM certificates");
        if ($stmt->fetchColumn() > 0) return 0;

        $now = date('Y-m-d H:i:s');
        $certs = [
            ['slug' => 'msme', 'title' => 'MSME Registered', 'issuer' => 'Ministry of MSME, Govt of India', 'image' => '/img/certification-1.png'],
            ['slug' => 'apeda', 'title' => 'APEDA Certified', 'issuer' => 'Ministry of Commerce & Industry', 'image' => '/img/certification-2.png'],
            ['slug' => 'iec', 'title' => 'Import Export Code (IEC)', 'issuer' => 'DGFT, Govt of India', 'image' => '/img/certification-3.png'],
            ['slug' => 'trademark', 'title' => 'Registered Trademark', 'issuer' => 'Controller General of Patents, Designs & Trade Marks', 'image' => '/img/certification-4.png'],
            ['slug' => 'udyam', 'title' => 'Udyam Registration', 'issuer' => 'Ministry of Micro, Small & Medium Enterprises', 'image' => '/img/udyam-adhar-registration-1024x659.webp'],
        ];

        $stmt = $this->pdo->prepare("INSERT INTO certificates (slug, title, issuer, image, status, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $order = 1;
        foreach ($certs as $c) {
            $stmt->execute([$c['slug'], $c['title'], $c['issuer'], $c['image'], 'published', $order++, $now, $now]);
        }
        ($this->out)("  + Seeded 5 Certifications");
        return count($certs);
    }

    private function seedTeam(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM team_members");
        if ($stmt->fetchColumn() > 0) return 0;

        $now = date('Y-m-d H:i:s');
        $members = [
            ['slug' => 'founder-ceo', 'name' => 'Rajesh Sharma', 'role' => 'Founder & CEO', 'bio' => '20+ years of expertise in global trade & export management.', 'photo' => '/img/team-1.webp'],
            ['slug' => 'head-export', 'name' => 'Sunita Das', 'role' => 'Head of Global Export', 'bio' => 'Specializes in international logistics and client compliance.', 'photo' => '/img/team-2.webp'],
            ['slug' => 'quality-chief', 'name' => 'Amitav Roy', 'role' => 'Chief Quality Auditor', 'bio' => 'Ensures premium craftsmanship standards across all product lines.', 'photo' => '/img/team-3.webp'],
            ['slug' => 'craft-director', 'name' => 'Priya Sengupta', 'role' => 'Artisanal Craft Director', 'bio' => 'Empowering local artisans and traditional Bengal heritage crafts.', 'photo' => '/img/team-4.webp'],
        ];

        $stmt = $this->pdo->prepare("INSERT INTO team_members (slug, name, role, photo, bio, status, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $order = 1;
        foreach ($members as $m) {
            $stmt->execute([$m['slug'], $m['name'], $m['role'], $m['photo'], $m['bio'], 'published', $order++, $now, $now]);
        }
        ($this->out)("  + Seeded 4 Team Members");
        return count($members);
    }

    private function seedTestimonials(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM testimonials");
        if ($stmt->fetchColumn() > 0) return 0;

        $now = date('Y-m-d H:i:s');
        $tests = [
            ['public_id' => 'tst_001', 'name' => 'Hans Müller', 'role' => 'Import Manager', 'company' => 'EuroCraft Imports (Germany)', 'text' => 'BCE Export has delivered exceptional quality wooden handicrafts and Dhokra artifacts. Timely delivery and superb packaging!', 'rating' => 5, 'photo' => '/img/testimonial-1.webp'],
            ['public_id' => 'tst_002', 'name' => 'Sophia Chen', 'role' => 'Procurement Director', 'company' => 'Pacific Home & Living (Singapore)', 'text' => 'Their genuine leather goods and eco-friendly jute collections are top seller items in our retail stores. Highly recommended partner!', 'rating' => 5, 'photo' => '/img/testimonial-2.webp'],
            ['public_id' => 'tst_003', 'name' => 'David Miller', 'role' => 'Global Sourcing Lead', 'company' => 'Artisan Trading Co. (USA)', 'text' => 'Reliable, certified exporter with transparent communication. The terracotta items arrived in 100% pristine condition.', 'rating' => 5, 'photo' => '/img/testimonial-3.webp'],
            ['public_id' => 'tst_004', 'name' => 'Elena Rostova', 'role' => 'Design Director', 'company' => 'Nordic Living (Sweden)', 'text' => 'We sourced handcrafted furniture and jute decor. The craftsmanship exceeded our expectations and customer feedback is tremendous.', 'rating' => 5, 'photo' => '/img/testimonial-4.webp'],
        ];

        $stmt = $this->pdo->prepare("INSERT INTO testimonials (public_id, name, role, company, text, rating, photo, featured, status, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $order = 1;
        foreach ($tests as $t) {
            $stmt->execute([$t['public_id'], $t['name'], $t['role'], $t['company'], $t['text'], $t['rating'], $t['photo'], 1, 'published', $order++, $now, $now]);
        }
        ($this->out)("  + Seeded 4 Client Testimonials");
        return count($tests);
    }

    private function seedProducts(): int
    {
        $now = date('Y-m-d H:i:s');
        $jsonFile = __DIR__ . '/../tools/products-master.json';
        $products = [];
        if (file_exists($jsonFile)) {
            $products = json_decode(file_get_contents($jsonFile), true) ?: [];
        }

        if (empty($products)) {
            // Fallback default products array
            $products = [
                ['slug' => 'jacket', 'name' => 'Jacket', 'category_id' => 'leather', 'short_description' => 'Premium handcrafted leather jacket.', 'description' => 'Tailored leather jacket for export.', 'image' => '/img/Leather 3.webp', 'featured' => 1, 'status' => 'published', 'sort_order' => 1],
                ['slug' => 'shoes', 'name' => 'Shoes', 'category_id' => 'leather', 'short_description' => 'Handcrafted formal leather shoes.', 'description' => 'Hand-stitched oxford dress shoes.', 'image' => '/img/Leather 4.webp', 'featured' => 1, 'status' => 'published', 'sort_order' => 2],
            ];
        }

        $checkStmt = $this->pdo->prepare("SELECT id FROM products WHERE slug = ?");
        $insertStmt = $this->pdo->prepare("INSERT INTO products (slug, name, category_id, short_description, description, image, featured, status, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $updateStmt = $this->pdo->prepare("UPDATE products SET name = ?, category_id = ?, short_description = ?, description = ?, image = ?, featured = ?, status = ?, sort_order = ?, updated_at = ? WHERE slug = ?");

        $inserted = 0;
        foreach ($products as $p) {
            $slug = $p['slug'];
            $checkStmt->execute([$slug]);
            if ($checkStmt->fetch()) {
                $updateStmt->execute([
                    $p['name'],
                    $p['category_id'],
                    $p['short_description'] ?? '',
                    $p['description'] ?? '',
                    $p['image'] ?? '',
                    !empty($p['featured']) ? 1 : 0,
                    $p['status'] ?? 'published',
                    $p['sort_order'] ?? 1,
                    $now,
                    $slug
                ]);
            } else {
                $insertStmt->execute([
                    $slug,
                    $p['name'],
                    $p['category_id'],
                    $p['short_description'] ?? '',
                    $p['description'] ?? '',
                    $p['image'] ?? '',
                    !empty($p['featured']) ? 1 : 0,
                    $p['status'] ?? 'published',
                    $p['sort_order'] ?? 1,
                    $now,
                    $now
                ]);
                $inserted++;
            }
        }
        ($this->out)("  + Seeded/Upserted " . count($products) . " Export Products across 7 Categories ({$inserted} new)");
        return count($products);
    }
}
