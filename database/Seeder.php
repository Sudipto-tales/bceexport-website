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
        $count += $this->seedBlog();
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

    private function seedBlog(): int
    {
        $now = date('Y-m-d H:i:s');
        
        $categories = [
            ['slug' => 'export-insights', 'name' => 'Export & Sourcing Insights', 'description' => 'Trends, market analysis, and sourcing strategies for international buyers.'],
            ['slug' => 'handicraft-guides', 'name' => 'Handicraft & Material Guides', 'description' => 'Craftsmanship insights on Indian metalwork, leather, jute, terracotta, and wood.'],
            ['slug' => 'quality-logistics', 'name' => 'Quality & Global Shipping', 'description' => 'Logistics, packaging, customs compliance, and quality control best practices.'],
        ];

        $catCheck = $this->pdo->prepare("SELECT id FROM blog_categories WHERE slug = ?");
        $catInsert = $this->pdo->prepare("INSERT INTO blog_categories (slug, name, description, status, sort_order, created_at, updated_at) VALUES (?, ?, ?, 'published', ?, ?, ?)");
        $catOrder = 1;
        foreach ($categories as $cat) {
            $catCheck->execute([$cat['slug']]);
            if (!$catCheck->fetch()) {
                $catInsert->execute([$cat['slug'], $cat['name'], $cat['description'], $catOrder++, $now, $now]);
            }
        }

        $posts = [
            [
                'slug' => 'how-to-import-indian-dhokra-handicrafts',
                'title' => 'How to Import Indian Dhokra Handicrafts: A Step-by-Step Sourcing Guide',
                'category_id' => 'handicraft-guides',
                'excerpt' => 'Discover the heritage of Bankura Dhokra lost-wax metal art, quality inspection techniques, packaging requirements, and export logistics for international buyers.',
                'body' => '<p>Dhokra (also spelled Dokra) art is one of India\'s oldest non-ferrous metal casting techniques, dating back over 4,000 years to the Indus Valley Civilization. Crafted primarily in the Bankura district of West Bengal, these handcrafted brass and bronze artifacts are highly prized by art collectors, interior designers, and global giftware importers.</p><h3>Why Source Dhokra Metal Crafts?</h3><p>Unlike mass-produced cast items, every single Dhokra piece is handcrafted using a traditional lost-wax technique (cire perdue). Because the clay mold must be broken to retrieve the cast artifact, every piece produced is completely unique.</p><h3>Key Quality Control Considerations for Exporters</h3><ul><li><b>Metal Composition:</b> High quality brass/bronze alloys ensure durability and signature golden patina without brittle spots.</li><li><b>Detailing & Finish:</b> Fine wax wire detailing on figurines, horses, elephants, and idols indicates master artisan craft.</li><li><b>Protective Packaging:</b> Individual bubble wraps and rigid outer master cartons prevent surface abrasion during long ocean freight container transits.</li></ul><p>At BCE Export, we work directly with Bankura artisan clusters to ensure strict quality standards, fair trade practices, and complete documentation for international customs clearance.</p>',
                'cover_image' => '/img/Dhokra Handcrafted.webp',
                'author_name' => 'BCE Export Team',
                'tags' => json_encode(['dhokra', 'handicrafts', 'import-guide', 'metal-art']),
                'featured' => 1,
                'published_at' => '2026-09-20 10:00:00',
            ],
            [
                'slug' => 'jute-vs-plastic-packaging-for-export',
                'title' => 'Jute vs Plastic Packaging for Export: Sustainable Sourcing for Global Brands',
                'category_id' => 'export-insights',
                'excerpt' => 'Why international importers are shifting to eco-friendly Indian jute bags, rugs, and packaging solutions to meet modern environmental compliance.',
                'body' => '<p>With global supply chains prioritizing environmental, social, and governance (ESG) compliance, packaging materials are under intense scrutiny. Plastic wraps and synthetic containers are increasingly subject to import tariffs, plastic taxes, and consumer pushback—especially across Europe and North America.</p><h3>The Golden Fiber Advantage</h3><p>India is the world\'s largest producer of raw jute, often known as the "Golden Fiber." Jute is 100% bio-degradable, compostable, and carbon-neutral, making it the ideal eco-friendly material for commercial packaging and lifestyle products.</p><h3>Key Importer Benefits:</h3><ul><li><b>Regulatory Compliance:</b> Meets EU packaging waste directives and plastic reduction laws.</li><li><b>High Tensile Strength:</b> Jute sacks and tote bags withstand heavy export loads without tearing.</li><li><b>Custom Branding:</b> Water-based screen printing on natural jute fabric offers premium branding for wholesale retail.</li></ul><p>Explore BCE Export\'s full range of customizable jute bags, shopping totes, and industrial sacks built for international distribution.</p>',
                'cover_image' => '/img/Handcrafted_jute.webp',
                'author_name' => 'Sudipta Ghosh',
                'tags' => json_encode(['jute', 'sustainability', 'packaging', 'export-trends']),
                'featured' => 1,
                'published_at' => '2026-09-22 14:30:00',
            ],
            [
                'slug' => 'quality-checks-for-terracotta-shipments',
                'title' => 'Essential Quality Checks for Terracotta & Clay Pottery Shipments',
                'category_id' => 'quality-logistics',
                'excerpt' => 'Prevent breakage during international sea freight with proper moisture testing, shock-absorbent packaging, and palletization standards.',
                'body' => '<p>Terracotta pottery and decorative clay artifacts from West Bengal are sought after globally for home decor, garden design, and cultural exhibitions. However, clay products are inherently brittle, making packaging and moisture management vital during maritime transport.</p><h3>Pre-Shipment Inspection Protocol</h3><ol><li><b>Kiln Firing Uniformity:</b> Ensuring clay pieces undergo high-temperature firing eliminates structural micro-cracks.</li><li><b>Moisture Content Check:</b> Terracotta must be thoroughly dried to less than 2% moisture prior to packing to prevent mold during damp sea voyages.</li><li><b>Corner & Drop Tests:</b> Master boxes must pass drop tests with heavy internal foam cushioning.</li></ol><p>BCE Export implements palletized shrink wrapping and custom wood crates for all bulk terracotta shipments to ensure zero damage upon arrival at destination ports.</p>',
                'cover_image' => '/img/Terracotta01.webp',
                'author_name' => 'Quality Inspection Bureau',
                'tags' => json_encode(['terracotta', 'quality-control', 'packaging', 'logistics']),
                'featured' => 0,
                'published_at' => '2026-09-25 11:15:00',
            ],
            [
                'slug' => 'sourcing-genuine-leather-goods-from-india',
                'title' => 'Sourcing Genuine Leather Goods from India: Quality & Craftsmanship Standards',
                'category_id' => 'handicraft-guides',
                'excerpt' => 'Key factors to evaluate when importing leather bags, jackets, footwear, and accessories directly from certified Indian manufacturers.',
                'body' => '<p>India is globally recognized for high-grade leather craftsmanship, supplying luxury fashion houses and commercial buyers worldwide. From full-grain leather bags to precision-stitched jackets and formal footwear, Indian leather combines durability with refined aesthetics.</p><h3>Understanding Leather Grades for Wholesale</h3><p>When placing bulk orders, importers should specify grain type, tanning method (vegetable-tanned vs chrome-tanned), and hardware specifications (brass or stainless steel zippers).</p><p>BCE Export guarantees 100% genuine leather sourcing with REACH-compliant tanning procedures suitable for international distribution in the EU and US markets.</p>',
                'cover_image' => '/img/Leather01.webp',
                'author_name' => 'Export Operations',
                'tags' => json_encode(['leather', 'sourcing', 'handicrafts', 'fashion-export']),
                'featured' => 0,
                'published_at' => '2026-09-26 16:45:00',
            ]
        ];

        $postCheck = $this->pdo->prepare("SELECT id FROM blog_posts WHERE slug = ?");
        $postInsert = $this->pdo->prepare("INSERT INTO blog_posts (slug, title, category_id, excerpt, body, cover_image, author_name, tags, featured, status, published_at, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', ?, ?, ?, ?)");
        
        $postOrder = 1;
        $insertedCount = 0;
        foreach ($posts as $p) {
            $postCheck->execute([$p['slug']]);
            if (!$postCheck->fetch()) {
                $postInsert->execute([
                    $p['slug'],
                    $p['title'],
                    $p['category_id'],
                    $p['excerpt'],
                    $p['body'],
                    $p['cover_image'],
                    $p['author_name'],
                    $p['tags'],
                    $p['featured'],
                    $p['published_at'],
                    $postOrder++,
                    $now,
                    $now
                ]);
                $insertedCount++;
            }
        }

        ($this->out)("  + Seeded Blog Categories & {$insertedCount} Blog Posts");
        return count($posts);
    }
}
