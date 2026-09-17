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
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM categories");
        if ($stmt->fetchColumn() > 0) return 0;

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
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM products");
        if ($stmt->fetchColumn() > 0) return 0;

        $now = date('Y-m-d H:i:s');
        $products = [
            // Leather
            [
                'slug' => 'genuine-leather-jacket',
                'name' => 'Genuine Leather Jacket',
                'category_id' => 'leather',
                'short_description' => 'Premium handcrafted leather jacket for international export.',
                'description' => 'Expertly tailored from select full-grain leather, combining classic design with contemporary durability. Features brass zip hardware and silk lining.',
                'image' => '/img/Leather 3.webp',
                'featured' => 1
            ],
            [
                'slug' => 'handcrafted-leather-shoes',
                'name' => 'Handcrafted Leather Shoes',
                'category_id' => 'leather',
                'short_description' => 'Formal oxford shoes in genuine calfskin leather.',
                'description' => 'Hand-stitched leather dress shoes offering superior comfort, durability, and a polished finish suitable for luxury retail markets worldwide.',
                'image' => '/img/Leather 4.webp',
                'featured' => 1
            ],
            [
                'slug' => 'classic-leather-hat',
                'name' => 'Classic Leather Hat',
                'category_id' => 'leather',
                'short_description' => 'Vintage finish genuine leather fedora hat.',
                'description' => 'Durable water-resistant leather hat crafted with artisanal care, popular across international western and vintage fashion stores.',
                'image' => '/img/Leather 5.webp',
                'featured' => 0
            ],
            [
                'slug' => 'executive-leather-briefcase',
                'name' => 'Executive Leather Briefcase',
                'category_id' => 'leather',
                'short_description' => 'Professional multi-compartment leather laptop briefcase.',
                'description' => 'Crafted from rich top-grain leather with dedicated laptop padding, document dividers, and a detachable padded shoulder strap.',
                'image' => '/img/Leather 6.webp',
                'featured' => 1
            ],

            // Wooden Handicraft
            [
                'slug' => 'hand-carved-wooden-peacock',
                'name' => 'Hand-Carved Wooden Peacock',
                'category_id' => 'wooden-handicraft',
                'short_description' => 'Intricate hand-carved peacock figurine in seasoned wood.',
                'description' => 'Showcasing intricate plumage details, hand-carved by Bengal master artisans. Polished with natural organic wax for lasting beauty.',
                'image' => '/img/wooden Handicraft-1.webp',
                'featured' => 1
            ],
            [
                'slug' => 'royal-wooden-elephant-statue',
                'name' => 'Royal Wooden Elephant Statue',
                'category_id' => 'wooden-handicraft',
                'short_description' => 'Traditional auspicious elephant with royal ambabari carving.',
                'description' => 'Carved from single-block seasoned hardwood with delicate filigree lattice work. An iconic symbol of Indian artistic heritage.',
                'image' => '/img/wooden Handicraft-2.webp',
                'featured' => 1
            ],
            [
                'slug' => 'carved-wooden-wall-panel',
                'name' => 'Carved Wooden Wall Panel',
                'category_id' => 'wooden-handicraft',
                'short_description' => 'Decorative carved floral teak wall art plaque.',
                'description' => 'Exquisite geometric and floral carving that adds luxury warmth to living spaces, hotel lobbies, and boutique hospitality interiors.',
                'image' => '/img/wooden Handicraft-3.webp',
                'featured' => 0
            ],

            // Furniture
            [
                'slug' => 'handcrafted-teak-dining-chair',
                'name' => 'Handcrafted Teak Dining Chair',
                'category_id' => 'furniture',
                'short_description' => 'Ergonomic solid teak dining chair with cane back.',
                'description' => 'Constructed from sustainably harvested Indian teakwood with hand-woven natural rattan backrest for timeless Scandinavian-Indian fusion.',
                'image' => '/img/Furniture_1.webp',
                'featured' => 1
            ],
            [
                'slug' => 'traditional-royal-armchair',
                'name' => 'Traditional Royal Armchair',
                'category_id' => 'furniture',
                'short_description' => 'Classic colonial style hardwood armchair with plush seating.',
                'description' => 'Elegantly sculpted armrests and sturdy frame, upholstered in durable export-grade linen fabric for residential and lounge settings.',
                'image' => '/img/Furniture_2.webp',
                'featured' => 1
            ],
            [
                'slug' => 'hand-carved-coffee-table',
                'name' => 'Hand-Carved Coffee Table',
                'category_id' => 'furniture',
                'short_description' => 'Solid sheesham wood coffee table with brass inlays.',
                'description' => 'Sturdy centerpiece table with hand-carved side aprons and protective polyurethane finish, flat-packed for safe international freight.',
                'image' => '/img/Furniture_3.webp',
                'featured' => 0
            ],

            // Jute
            [
                'slug' => 'eco-friendly-jute-tote-bag',
                'name' => 'Eco-Friendly Jute Shopping Tote',
                'category_id' => 'jute',
                'short_description' => '100% biodegradable natural golden jute shopping bag.',
                'description' => 'Reinforced cotton handles and laminated water-resistant interior. An eco-conscious alternative to plastic bags for global retailers.',
                'image' => '/img/jute_1.webp',
                'featured' => 1
            ],
            [
                'slug' => 'braided-jute-storage-basket',
                'name' => 'Braided Jute Storage Basket',
                'category_id' => 'jute',
                'short_description' => 'Hand-braided circular storage organizer basket.',
                'description' => 'Artisanal storage solution woven from sun-dried natural golden jute fiber, perfect for modern bohemian home interiors.',
                'image' => '/img/jute_2.webp',
                'featured' => 1
            ],
            [
                'slug' => 'designer-embroidered-jute-handbag',
                'name' => 'Designer Embroidered Jute Handbag',
                'category_id' => 'jute',
                'short_description' => 'Fashion jute handbag with traditional thread embroidery.',
                'description' => 'Combines ethnic Bengal threadwork with contemporary handbag styling, zippered main compartment, and comfortable shoulder straps.',
                'image' => '/img/jute_3.webp',
                'featured' => 0
            ],

            // Dhokra
            [
                'slug' => 'dhokra-tribal-musician-couple',
                'name' => 'Dhokra Tribal Musician Couple',
                'category_id' => 'dhokra',
                'short_description' => 'Antique lost-wax cast brass tribal musicians.',
                'description' => 'Cast using ancient 4,000-year-old lost-wax method. Each piece is completely one-of-a-kind, celebrating folk music traditions.',
                'image' => '/img/Dhokra Handcrafted_1.webp',
                'featured' => 1
            ],
            [
                'slug' => 'dhokra-tribal-deer-figurine',
                'name' => 'Dhokra Tribal Deer Figurine',
                'category_id' => 'dhokra',
                'short_description' => 'Handcrafted non-ferrous bell metal deer sculpture.',
                'description' => 'Exquisitely coiled metal threads forming the body of a graceful forest deer, finished in authentic antique bronze patina.',
                'image' => '/img/Dhokra Handcrafted_2.webp',
                'featured' => 1
            ],
            [
                'slug' => 'dhokra-war-horse-figurine',
                'name' => 'Dhokra War Horse Figurine',
                'category_id' => 'dhokra',
                'short_description' => 'Traditional ceremonial horse sculpture in bell metal.',
                'description' => 'A powerful symbol of royalty and folk mythology, decorated with traditional tribal motifs and intricate open-lattice casting.',
                'image' => '/img/Dhokra Handcrafted_3.webp',
                'featured' => 0
            ],

            // Terracotta
            [
                'slug' => 'terracotta-flower-vase',
                'name' => 'Terracotta Flower Vase',
                'category_id' => 'terracotta',
                'short_description' => 'Wheel-thrown clay vase with handcrafted relief etching.',
                'description' => 'Kiln-fired natural Bengal terracotta clay vase, showcasing artisanal hand-carved floral patterns with a natural earthy finish.',
                'image' => '/img/Terracotta Products_1.webp',
                'featured' => 1
            ],
            [
                'slug' => 'terracotta-diya-lamp',
                'name' => 'Terracotta Decorative Diya Lamp',
                'category_id' => 'terracotta',
                'short_description' => 'Ornate handcrafted multi-tier clay oil diya lamp.',
                'description' => 'Ideal for festive decor and aromatherapy, hand-molded and sun-cured before high-temperature firing for long-lasting durability.',
                'image' => '/img/Terracotta Products_2.webp',
                'featured' => 1
            ],
            [
                'slug' => 'terracotta-artisan-wall-plaque',
                'name' => 'Terracotta Artisan Wall Plaque',
                'category_id' => 'terracotta',
                'short_description' => 'Traditional relief clay wall hanging plaque.',
                'description' => 'Depicting rural folk village life and motifs, designed with rear hanging hook for easy interior wall installation.',
                'image' => '/img/Terracotta Products_3.webp',
                'featured' => 0
            ],

            // Fruit & Vegetable
            [
                'slug' => 'export-quality-organic-okra',
                'name' => 'Export Quality Organic Okra',
                'category_id' => 'fruit-vegetable',
                'short_description' => 'Tender farm-fresh organic ladyfinger for export.',
                'description' => 'Harvested at peak tenderness, pesticide-tested, sorted and packaged in temperature-controlled corrugated cartons for air freight.',
                'image' => '/img/Vegetable-1.webp',
                'featured' => 1
            ],
            [
                'slug' => 'premium-indian-green-chilli',
                'name' => 'Premium Indian Green Chilli',
                'category_id' => 'fruit-vegetable',
                'short_description' => 'Spicy G4 export-grade green chillies with fresh stems.',
                'description' => 'Crisp, vibrant green chillies carefully washed, sorted, and packed in ventilated cartons meeting strict international phytosanitary rules.',
                'image' => '/img/Vegetable-2.webp',
                'featured' => 1
            ],
            [
                'slug' => 'farm-fresh-bitter-gourd',
                'name' => 'Farm-Fresh Bitter Gourd (Karela)',
                'category_id' => 'fruit-vegetable',
                'short_description' => 'Dark green organically cultivated bitter gourd.',
                'description' => 'Rich in nutrients and freshness, sorted by size and grade, ready for direct supply to ethnic retail distributors worldwide.',
                'image' => '/img/Vegetable-3.webp',
                'featured' => 0
            ]
        ];

        $stmt = $this->pdo->prepare("INSERT INTO products (slug, name, category_id, short_description, description, image, featured, status, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $order = 1;
        foreach ($products as $p) {
            $stmt->execute([
                $p['slug'],
                $p['name'],
                $p['category_id'],
                $p['short_description'],
                $p['description'],
                $p['image'],
                $p['featured'],
                'published',
                $order++,
                $now,
                $now
            ]);
        }
        ($this->out)("  + Seeded " . count($products) . " Export Products across 7 Categories");
        return count($products);
    }
}
