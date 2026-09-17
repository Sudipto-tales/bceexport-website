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
                'favicon' => '/img/favicon.png'
            ],
            'contact' => [
                'phone' => '+91 9876543210',
                'whatsapp' => '+91 9876543210',
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
                'facebook' => 'https://facebook.com/bceexport',
                'instagram' => 'https://instagram.com/bceexport',
                'linkedin' => 'https://linkedin.com/company/bceexport',
                'whatsapp' => 'https://wa.me/919876543210'
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
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM categories");
        if ($stmt->fetchColumn() > 0) return 0;

        $now = date('Y-m-d H:i:s');
        $categories = [
            ['slug' => 'leather', 'name' => 'Leather Goods', 'description' => 'Premium handcrafted genuine leather products & bags.', 'image' => '/img/leather-cat.webp'],
            ['slug' => 'wooden-handicraft', 'name' => 'Wooden Handicraft', 'description' => 'Exquisite hand-carved wooden decorative items.', 'image' => '/handicraft1.webp'],
            ['slug' => 'furniture', 'name' => 'Furniture', 'description' => 'Royal handcrafted wooden & cane furniture.', 'image' => '/Furniture_Main.webp'],
            ['slug' => 'jute', 'name' => 'Jute Products', 'description' => 'Eco-friendly sustainable jute bags & home decor.', 'image' => '/decorative.webp'],
            ['slug' => 'dhokra', 'name' => 'Dhokra Art', 'description' => 'Ancient non-ferrous metal casting art form of Bengal.', 'image' => '/img/dhokra-cat.webp'],
            ['slug' => 'terracotta', 'name' => 'Terracotta', 'description' => 'Traditional clay pottery, statues & wall tiles.', 'image' => '/img/terracotta-cat.webp'],
            ['slug' => 'fruit-vegetable', 'name' => 'Fruit & Vegetable', 'description' => 'Fresh, organic export-quality agricultural produce.', 'image' => '/img/fv-cat.webp'],
        ];

        $stmt = $this->pdo->prepare("INSERT INTO categories (slug, name, description, image, status, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $order = 1;
        foreach ($categories as $cat) {
            $stmt->execute([$cat['slug'], $cat['name'], $cat['description'], $cat['image'], 'published', $order++, $now, $now]);
        }
        ($this->out)("  + Seeded 7 Product Categories");
        return count($categories);
    }

    private function seedCertificates(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM certificates");
        if ($stmt->fetchColumn() > 0) return 0;

        $now = date('Y-m-d H:i:s');
        $certs = [
            ['slug' => 'msme', 'title' => 'MSME Registered', 'issuer' => 'Ministry of MSME, Govt of India', 'image' => '/img/msme-cert.webp'],
            ['slug' => 'apeda', 'title' => 'APEDA Certified', 'issuer' => 'Ministry of Commerce & Industry', 'image' => '/img/apeda-cert.webp'],
            ['slug' => 'iec', 'title' => 'Import Export Code (IEC)', 'issuer' => 'DGFT, Govt of India', 'image' => '/img/iec-cert.webp'],
            ['slug' => 'trademark', 'title' => 'Registered Trademark', 'issuer' => 'Controller General of Patents, Designs & Trade Marks', 'image' => '/img/trademark-cert.webp'],
        ];

        $stmt = $this->pdo->prepare("INSERT INTO certificates (slug, title, issuer, image, status, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $order = 1;
        foreach ($certs as $c) {
            $stmt->execute([$c['slug'], $c['title'], $c['issuer'], $c['image'], 'published', $order++, $now, $now]);
        }
        ($this->out)("  + Seeded 4 Certifications");
        return count($certs);
    }

    private function seedTeam(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM team_members");
        if ($stmt->fetchColumn() > 0) return 0;

        $now = date('Y-m-d H:i:s');
        $members = [
            ['slug' => 'founder-ceo', 'name' => 'Rajesh Sharma', 'role' => 'Founder & CEO', 'bio' => '20+ years of expertise in global trade & export management.', 'photo' => '/img/team1.jpg'],
            ['slug' => 'head-export', 'name' => 'Sunita Das', 'role' => 'Head of Global Export', 'bio' => 'Specializes in international logistics and client compliance.', 'photo' => '/img/team2.jpg'],
            ['slug' => 'quality-chief', 'name' => 'Amitav Roy', 'role' => 'Chief Quality Auditor', 'bio' => 'Ensures premium craftsmanship standards across all product lines.', 'photo' => '/img/team3.jpg'],
            ['slug' => 'craft-director', 'name' => 'Priya Sengupta', 'role' => 'Artisanal Craft Director', 'bio' => 'Empowering local artisans and traditional Bengal heritage crafts.', 'photo' => '/img/team4.jpg'],
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
            ['public_id' => 'tst_001', 'name' => 'Hans Müller', 'role' => 'Import Manager', 'company' => 'EuroCraft Imports (Germany)', 'text' => 'BCE Export has delivered exceptional quality wooden handicrafts and Dhokra artifacts. Timely delivery and superb packaging!', 'rating' => 5, 'photo' => '/img/client1.jpg'],
            ['public_id' => 'tst_002', 'name' => 'Sophia Chen', 'role' => 'Procurement Director', 'company' => 'Pacific Home & Living (Singapore)', 'text' => 'Their genuine leather goods and eco-friendly jute collections are top seller items in our retail stores. Highly recommended partner!', 'rating' => 5, 'photo' => '/img/client2.jpg'],
            ['public_id' => 'tst_003', 'name' => 'David Miller', 'role' => 'Global Sourcing Lead', 'company' => 'Artisan Trading Co. (USA)', 'text' => 'Reliable, certified exporter with transparent communication. The terracotta items arrived in 100% pristine condition.', 'rating' => 5, 'photo' => '/img/client3.jpg'],
        ];

        $stmt = $this->pdo->prepare("INSERT INTO testimonials (public_id, name, role, company, text, rating, photo, featured, status, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $order = 1;
        foreach ($tests as $t) {
            $stmt->execute([$t['public_id'], $t['name'], $t['role'], $t['company'], $t['text'], $t['rating'], $t['photo'], 1, 'published', $order++, $now, $now]);
        }
        ($this->out)("  + Seeded 3 Client Testimonials");
        return count($tests);
    }
}
