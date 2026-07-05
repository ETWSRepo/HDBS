<?php
// api/studio_seed.php — Design Studio schema + starter-content seed.
// Shared by api/studio.php (runtime) and regression_test.php (verification) so the
// table definitions and seed copy live in exactly one place.

function studioEnsureTables($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS studio_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section VARCHAR(12) NOT NULL,
        title VARCHAR(150) NOT NULL DEFAULT '',
        data TEXT,
        image VARCHAR(255) NOT NULL DEFAULT '',
        sort_order INT DEFAULT 0,
        active TINYINT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS studio_inquiries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL,
        phone VARCHAR(40) NOT NULL DEFAULT '',
        project_type VARCHAR(80) NOT NULL DEFAULT '',
        budget VARCHAR(60) NOT NULL DEFAULT '',
        timeline VARCHAR(60) NOT NULL DEFAULT '',
        description TEXT,
        contact_pref VARCHAR(20) NOT NULL DEFAULT '',
        inspiration TEXT,
        status VARCHAR(10) NOT NULL DEFAULT 'new',
        ip VARCHAR(45) NOT NULL DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// One-time seed: starter service cards + FAQs (placeholder copy for Suzi to review in the
// admin — galleries/projects/testimonials are never seeded; they stay hidden until real
// content is added).
function studioSeed($pdo) {
    $count = (int)$pdo->query("SELECT COUNT(*) FROM studio_items WHERE section='service'")->fetchColumn();
    if ($count === 0) {
        $services = [
            ['Custom Tote Bags', 'Handcrafted tote bags made from premium fabrics, including one-of-a-kind themed designs.', 'Everyday use, gifts, shopping, travel, and special occasions.', 'Corvette, floral, seasonal, patriotic, and personalized designs.'],
            ['Crossbody Bags', 'Stylish, lightweight bags designed for everyday convenience and hands-free carrying.', 'Travel, festivals, shopping, and daily use.', 'Compact crossbody bags with zipper pockets and adjustable straps.'],
            ['Custom Bags', 'Handmade bags in a variety of styles beyond totes and crossbodies, built around your fabric and features.', 'Handbags, wristlets, pouches, and everyday carry.', 'A quilted wristlet, a zippered pouch set, or a one-of-a-kind handbag.'],
            ['Custom Quilts', 'Beautiful handmade quilts created for family heirlooms, gifts, and everyday comfort.', 'Weddings, baby showers, anniversaries, and home décor.', 'Lap quilts, baby quilts, memory quilts, and seasonal designs.'],
            ['Custom Embroidery', 'Personalize bags, towels, blankets, hats, and more with names, monograms, or custom designs.', 'Birthdays, weddings, businesses, and personalized gifts.', 'Monograms, custom names, logos, and decorative embroidery.'],
            ['Memory Keepsakes', 'Transform meaningful clothing or fabric into treasured keepsakes you\'ll cherish for years.', 'Memorial gifts, baby clothes, graduation shirts, and family memories.', 'T-shirt quilts, memory pillows, keepsake bags, and embroidered remembrance gifts.'],
            ['Custom Sewing Projects', 'Have an idea? Let\'s create something completely unique just for you.', 'Special requests and custom commissions.', 'If you can imagine it, we\'ll work together to bring it to life.'],
        ];
        $ins = $pdo->prepare("INSERT INTO studio_items (section,title,data,sort_order) VALUES ('service',?,?,?)");
        foreach ($services as $i => $s) {
            $ins->execute([$s[0], json_encode(['desc'=>$s[1],'ideal'=>$s[2],'example'=>$s[3]]), $i]);
        }
    }
    $count = (int)$pdo->query("SELECT COUNT(*) FROM studio_items WHERE section='faq'")->fetchColumn();
    if ($count === 0) {
        $faqs = [
            ['How much does a custom commission cost?', 'Every project is quoted individually based on size, materials, and the time involved. After you share your idea, Suzi will send a clear quote before any work begins — no surprises.'],
            ['How long will my project take?', 'Timelines vary by project type and current workload. You\'ll get an estimated completion date with your quote, and Suzi keeps you updated at every stage.'],
            ['Can I request changes during the process?', 'Yes. Every commission includes a refinement stage where you review the work in progress and request adjustments before final delivery.'],
            ['Who owns the finished artwork?', 'You receive the finished piece, and for design work the print and usage rights are agreed up front. Suzi may share photos of the work in her portfolio unless you ask otherwise.'],
            ['Do you ship?', 'Yes — finished pieces are carefully packed and shipped to you. Local pickup or delivery may also be available in the Knoxville, TN area.'],
            ['Can I get digital files?', 'For design work like logos and branding you\'ll receive the standard digital files you need. Digital copies of artwork are available on request.'],
            ['Can you make something that isn\'t listed here?', 'Almost certainly. If it\'s creative, just ask — custom requests are the heart of the Design Studio.'],
            ['What happens in the consultation?', 'A friendly conversation — by email, phone, or text, whichever you prefer — about your vision, budget, and timeline. There\'s no commitment until you approve the quote.'],
            ['How does payment work?', 'Payment details are agreed along with your quote — typically a portion up front with the balance on completion. Options are discussed during the consultation.'],
            ['What is it like working together?', 'Personal and collaborative. You work one-on-one with Suzi from first idea to final delivery, and she reads and answers every message herself.'],
        ];
        $ins = $pdo->prepare("INSERT INTO studio_items (section,title,data,sort_order) VALUES ('faq',?,?,?)");
        foreach ($faqs as $i => $f) {
            $ins->execute([$f[0], json_encode(['answer'=>$f[1]]), $i]);
        }
    }
}
