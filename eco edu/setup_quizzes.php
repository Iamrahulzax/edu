<?php
// Setup script to seed EcoEdu quizzes with questions
try {
    require_once 'config/database.php';
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

echo "<h2>🧠 Populating EcoEdu Quizzes...</h2>";

try {
    // Ensure categories table exists with base data
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        icon VARCHAR(50) DEFAULT 'fas fa-book',
        color VARCHAR(20) DEFAULT '#007bff',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Ensure quizzes table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS quizzes (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(200) NOT NULL,
        description TEXT,
        category_id INT,
        difficulty_level ENUM('easy','medium','hard') DEFAULT 'easy',
        time_limit INT DEFAULT 300,
        total_questions INT DEFAULT 0,
        points_per_question INT DEFAULT 5,
        pass_percentage DECIMAL(5,2) DEFAULT 70.00,
        is_active BOOLEAN DEFAULT TRUE,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )");

    // Ensure quiz_questions table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_questions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        quiz_id INT NOT NULL,
        question TEXT NOT NULL,
        question_type ENUM('multiple_choice','true_false','fill_blank') DEFAULT 'multiple_choice',
        option_a VARCHAR(500),
        option_b VARCHAR(500),
        option_c VARCHAR(500),
        option_d VARCHAR(500),
        correct_answer VARCHAR(500) NOT NULL,
        explanation TEXT,
        points INT DEFAULT 5,
        order_index INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
    )");

    // Fetch categories map
    $stmt = $pdo->query("SELECT id, name FROM categories");
    $categories = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Helper to get or create category by name
    $getCategoryId = function(string $name, string $description, string $icon, string $color) use (&$categories, $pdo) {
        if ($categories) {
            $match = array_search($name, $categories, true);
            if ($match !== false) {
                return (int)$match;
            }
        }
        $stmt = $pdo->prepare("INSERT INTO categories (name, description, icon, color) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $description, $icon, $color]);
        $id = (int)$pdo->lastInsertId();
        $categories[$id] = $name;
        return $id;
    };

    // Ensure core categories exist
    $categorySeed = [
        ['Climate Change', 'Causes and effects of global climate change.', 'fas fa-temperature-high', '#dc3545'],
        ['Sustainability', 'Practices that support long-term ecological balance.', 'fas fa-leaf', '#198754'],
        ['Renewable Energy', 'Clean and renewable energy sources.', 'fas fa-solar-panel', '#ffc107'],
        ['Waste Management', 'Reduce, reuse, and recycle initiatives.', 'fas fa-recycle', '#0dcaf0'],
        ['Biodiversity', 'Protection of wildlife and natural habitats.', 'fas fa-paw', '#6f42c1'],
        ['Water Conservation', 'Protecting and conserving water resources.', 'fas fa-tint', '#0d6efd'],
        ['Green Lifestyle', 'Daily choices that reduce environmental impact.', 'fas fa-seedling', '#20c997'],
    ];

    foreach ($categorySeed as $entry) {
        $getCategoryId($entry[0], $entry[1], $entry[2], $entry[3]);
    }

    // Refresh categories map
    $stmt = $pdo->query("SELECT name, id FROM categories");
    $categoryNameToId = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $quizzes = [
        [
            'title' => 'Climate Change Basics',
            'description' => 'Test your understanding of the causes and impacts of climate change.',
            'category' => 'Climate Change',
            'difficulty' => 'easy',
            'time_limit' => 360,
            'points_per_question' => 10,
            'pass_percentage' => 70,
            'questions' => [
                ['What is the greenhouse effect?', 'A cooling process caused by ice caps', 'A natural process trapping heat in the atmosphere', 'A method of growing plants indoors', 'A human-made process that blocks sunlight', 'A natural process trapping heat in the atmosphere', 'Greenhouse gases trap heat and keep Earth warm.', 10],
                ['Which gas contributes the most to human-caused climate change?', 'Oxygen', 'Nitrogen', 'Carbon dioxide', 'Helium', 'Carbon dioxide', 'CO₂ is the primary greenhouse gas emitted by human activities.', 10],
                ['What is the main human activity increasing greenhouse gases?', 'Space exploration', 'Burning fossil fuels', 'Recycling programs', 'Planting trees', 'Burning fossil fuels', 'Burning coal, oil, and gas releases large amounts of CO₂.', 10],
                ['What is a major consequence of melting polar ice caps?', 'Lower sea levels', 'More freshwater for drinking', 'Sea level rise', 'Increased fish populations', 'Sea level rise', 'Melting ice adds water to the oceans, raising sea levels.', 10],
                ['How can individuals reduce their carbon footprint?', 'Leaving lights on', 'Using single-use plastics', 'Carpooling and using public transit', 'Burning more coal', 'Carpooling and using public transit', 'Shared transportation reduces emissions per person.', 10],
            ],
        ],
        [
            'title' => 'Renewable Energy Foundations',
            'description' => 'Explore the basics of clean, renewable sources of power.',
            'category' => 'Renewable Energy',
            'difficulty' => 'easy',
            'time_limit' => 360,
            'points_per_question' => 10,
            'pass_percentage' => 70,
            'questions' => [
                ['Which of the following is NOT a renewable energy source?', 'Solar energy', 'Wind energy', 'Natural gas', 'Hydropower', 'Natural gas', 'Natural gas is a fossil fuel and not renewable.', 10],
                ['What device converts sunlight directly into electricity?', 'Coal furnace', 'Photovoltaic cell', 'Wind turbine', 'Geothermal pump', 'Photovoltaic cell', 'Solar panels use photovoltaic cells to generate electricity.', 10],
                ['Which country currently leads in wind power capacity?', 'India', 'Germany', 'China', 'Brazil', 'China', 'China has the largest installed wind power capacity.', 10],
                ['What is the main advantage of renewable energy?', 'Unlimited supply and lower emissions', 'High emissions and pollution', 'It must be burned to create heat', 'It only works during the day', 'Unlimited supply and lower emissions', 'Renewables are naturally replenished and cleaner than fossil fuels.', 10],
                ['Geothermal energy taps into what resource?', 'The sun’s rays', 'Heat from within the Earth', 'Wind currents', 'Ocean tides', 'Heat from within the Earth', 'Geothermal plants use Earth’s internal heat.', 10],
            ],
        ],
        [
            'title' => 'Waste Management & Recycling',
            'description' => 'Learn best practices for reducing waste and recycling effectively.',
            'category' => 'Waste Management',
            'difficulty' => 'medium',
            'time_limit' => 420,
            'points_per_question' => 10,
            'pass_percentage' => 75,
            'questions' => [
                ['What is the correct order of the waste hierarchy?', 'Recycle, reuse, reduce', 'Reduce, reuse, recycle', 'Reuse, recycle, reduce', 'Recycle, reduce, reuse', 'Reduce, reuse, recycle', 'We should first reduce, then reuse, and finally recycle.', 10],
                ['Which material is commonly NOT accepted in curbside recycling?', 'Glass bottles', 'Plastic bags', 'Aluminum cans', 'Paper', 'Plastic bags', 'Plastic bags can jam recycling machinery and need special drop-off locations.', 10],
                ['What does composting primarily turn organic waste into?', 'Plastic', 'Fertilizer', 'Metal', 'Glass', 'Fertilizer', 'Composting creates nutrient-rich compost for soil.', 10],
                ['E-waste refers to waste from which items?', 'Clothing and textiles', 'Food scraps', 'Electronic devices', 'Plant matter', 'Electronic devices', 'E-waste includes discarded electronic devices like phones and computers.', 10],
                ['What is one benefit of a circular economy?', 'More landfills are needed', 'Products are designed to be disposable', 'Materials are kept in use longer', 'Resources are wasted faster', 'Materials are kept in use longer', 'A circular economy keeps materials circulating through reuse and recycling.', 10],
            ],
        ],
        [
            'title' => 'Water Conservation Strategies',
            'description' => 'Assess your knowledge of saving and protecting freshwater resources.',
            'category' => 'Water Conservation',
            'difficulty' => 'medium',
            'time_limit' => 420,
            'points_per_question' => 10,
            'pass_percentage' => 75,
            'questions' => [
                ['What percent of Earth’s water is readily accessible freshwater?', '10%', '25%', '3%', '50%', '3%', 'Only a small percentage of Earth’s water is accessible freshwater.', 10],
                ['Which household fixture typically uses the most water indoors?', 'Dishwasher', 'Toilet', 'Kitchen faucet', 'Clothes washer', 'Toilet', 'Toilets account for a large portion of indoor household water use.', 10],
                ['Rain barrels help conserve water by?', 'Evaporating water quickly', 'Collecting and storing rainwater for later use', 'Creating more rain', 'Boiling water for drinking', 'Collecting and storing rainwater for later use', 'Rain barrels capture rainfall for watering plants and gardens.', 10],
                ['Xeriscaping refers to?', 'Planting only vegetables', 'Using drought-tolerant landscaping', 'Removing all plants from yards', 'Watering plants daily', 'Using drought-tolerant landscaping', 'Xeriscaping reduces water usage by using native and drought-resistant plants.', 10],
                ['A simple way to detect toilet leaks at home is to?', 'Pour oil into the tank', 'Add food coloring to the tank and wait', 'Flush multiple times rapidly', 'Turn off the water supply entirely', 'Add food coloring to the tank and wait', 'If color appears in the bowl, there is a leak.', 10],
            ],
        ],
        [
            'title' => 'Biodiversity & Ecosystems',
            'description' => 'Understand why biodiversity matters and how ecosystems stay balanced.',
            'category' => 'Biodiversity',
            'difficulty' => 'medium',
            'time_limit' => 420,
            'points_per_question' => 10,
            'pass_percentage' => 75,
            'questions' => [
                ['What is biodiversity?', 'The number of trees in a forest', 'Variety of life in all its forms', 'Amount of rainfall in a region', 'Quantity of soil nutrients', 'Variety of life in all its forms', 'Biodiversity encompasses species, genetic, and ecosystem diversity.', 10],
                ['Why are pollinators important?', 'They reduce carbon dioxide', 'They spread seeds by swimming', 'They help plants reproduce by moving pollen', 'They eat harmful insects', 'They help plants reproduce by moving pollen', 'Pollinators enable many plants to produce fruits and seeds.', 10],
                ['An invasive species is?', 'Always beneficial', 'Native and endangered', 'Introduced species causing harm', 'Found only in zoos', 'Introduced species causing harm', 'Invasive species outcompete native species and disrupt ecosystems.', 10],
                ['What is a keystone species?', 'A species that is rarely seen', 'A species that exerts a strong influence on an ecosystem', 'A species kept in zoos only', 'A species with a short lifespan', 'A species that exerts a strong influence on an ecosystem', 'Removing keystone species can drastically change ecosystems.', 10],
                ['Habitat fragmentation occurs when?', 'Habitat becomes more connected', 'Large habitats are broken into smaller isolated patches', 'Animals migrate naturally', 'Forests are protected', 'Large habitats are broken into smaller isolated patches', 'Fragmentation isolates wildlife populations and reduces biodiversity.', 10],
            ],
        ],
        [
            'title' => 'Sustainable Living Habits',
            'description' => 'Identify daily choices that promote a greener lifestyle.',
            'category' => 'Green Lifestyle',
            'difficulty' => 'easy',
            'time_limit' => 300,
            'points_per_question' => 8,
            'pass_percentage' => 70,
            'questions' => [
                ['Which habit saves the most energy?', 'Leaving electronics plugged in', 'Air-drying clothes instead of using a dryer', 'Keeping lights on all night', 'Running the dishwasher half empty', 'Air-drying clothes instead of using a dryer', 'Air-drying reduces electricity use.', 8],
                ['Which diet has the lowest average carbon footprint?', 'Meat-heavy diet', 'Vegetarian or plant-based diet', 'Fast food only diet', 'Seafood-only diet', 'Vegetarian or plant-based diet', 'Plant-based diets typically emit fewer greenhouse gases.', 8],
                ['A reusable water bottle helps by?', 'Increasing plastic use', 'Reducing single-use plastic waste', 'Purifying tap water automatically', 'Producing electricity', 'Reducing single-use plastic waste', 'Reusable bottles decrease the need for disposable plastics.', 8],
                ['Smart thermostats save energy by?', 'Keeping the temperature constant all day', 'Automatically adjusting heating and cooling when you are away', 'Using more electricity than older thermostats', 'Turning on lights automatically', 'Automatically adjusting heating and cooling when you are away', 'Smart thermostats optimize energy use based on occupancy.', 8],
                ['Buying products with minimal packaging helps because?', 'It increases manufacturing costs', 'It reduces waste and resource use', 'It makes products heavier', 'It lowers product durability', 'It reduces waste and resource use', 'Less packaging means fewer materials used and less waste.', 8],
            ],
        ],
        [
            'title' => 'Climate Action Heroes',
            'description' => 'Learn about global initiatives and leaders tackling climate change.',
            'category' => 'Climate Change',
            'difficulty' => 'medium',
            'time_limit' => 420,
            'points_per_question' => 10,
            'pass_percentage' => 75,
            'questions' => [
                ['What was the aim of the Paris Agreement?', 'Increase fossil fuel subsidies', 'Limit global warming well below 2°C', 'Eliminate renewable energy programs', 'Support deforestation', 'Limit global warming well below 2°C', 'The Paris Agreement seeks to limit warming to below 2°C above pre-industrial levels.', 10],
                ['Which organization publishes the major climate science assessment reports?', 'WHO', 'IMF', 'IPCC', 'UNESCO', 'IPCC', 'The Intergovernmental Panel on Climate Change provides scientific assessments.', 10],
                ['Greta Thunberg is known for?', 'Leading a plastic manufacturing company', 'Founding a major oil firm', 'Inspiring global climate strikes', 'Inventing solar panels', 'Inspiring global climate strikes', 'Greta sparked worldwide climate activism among youth.', 10],
                ['Which country pledged to reach carbon neutrality by 2060?', 'Australia', 'China', 'Canada', 'Argentina', 'China', 'China announced goals to peak emissions before 2030 and reach neutrality by 2060.', 10],
                ['Reforestation programs primarily help by?', 'Increasing air pollution', 'Capturing carbon dioxide from the atmosphere', 'Producing more plastic', 'Reducing biodiversity', 'Capturing carbon dioxide from the atmosphere', 'Trees absorb CO₂, acting as carbon sinks.', 10],
            ],
        ],
        [
            'title' => 'Ocean Guardians',
            'description' => 'Discover how oceans support life and how to protect them.',
            'category' => 'Biodiversity',
            'difficulty' => 'medium',
            'time_limit' => 420,
            'points_per_question' => 10,
            'pass_percentage' => 75,
            'questions' => [
                ['What causes ocean acidification?', 'Increased salt levels', 'Higher oxygen levels', 'Absorption of excess CO₂', 'Less sunlight reaching the ocean', 'Absorption of excess CO₂', 'CO₂ dissolving in seawater forms carbonic acid.', 10],
                ['Why are coral reefs called the “rainforests of the sea”?', 'They receive heavy rainfall', 'They host extraordinary biodiversity', 'They are made of trees', 'They produce rainfall', 'They host extraordinary biodiversity', 'Coral reefs support a vast variety of marine life.', 10],
                ['Marine protected areas (MPAs) help by?', 'Banning all fishing globally', 'Providing safe zones for marine life to recover', 'Draining oceans', 'Increasing plastic production', 'Providing safe zones for marine life to recover', 'MPAs limit damaging activities to restore ecosystems.', 10],
                ['Which practice helps reduce plastic pollution in oceans?', 'Using microbeads in cosmetics', 'Properly disposing of fishing gear', 'Dumping garbage at sea', 'Increasing single-use plastics', 'Properly disposing of fishing gear', 'Responsible disposal prevents plastic from entering oceans.', 10],
                ['Phytoplankton is important because it?', 'Produces most of Earth’s oxygen', 'Decreases biodiversity', 'Causes ocean pollution', 'Warms seawater', 'Produces most of Earth’s oxygen', 'These tiny plants photosynthesize and generate large amounts of oxygen.', 10],
            ],
        ],
        [
            'title' => 'Eco-Innovations',
            'description' => 'Explore cutting-edge technologies solving environmental challenges.',
            'category' => 'Sustainability',
            'difficulty' => 'hard',
            'time_limit' => 480,
            'points_per_question' => 12,
            'pass_percentage' => 80,
            'questions' => [
                ['What is a smart grid designed to do?', 'Increase blackout frequency', 'Optimize electricity distribution using digital tech', 'Reduce renewable integration', 'Operate only on fossil fuels', 'Optimize electricity distribution using digital tech', 'Smart grids balance supply and demand efficiently.', 12],
                ['Which innovation removes CO₂ directly from the air?', 'Coal gasification', 'Direct air capture technology', 'Diesel generators', 'Biofuels', 'Direct air capture technology', 'DAC systems capture CO₂ for storage or reuse.', 12],
                ['Vertical farming primarily helps cities by?', 'Increasing deforestation', 'Producing fresh food with less land and water', 'Releasing more pesticides', 'Eliminating indoor agriculture', 'Producing fresh food with less land and water', 'Vertical farms yield produce in controlled environments.', 12],
                ['Circular design in manufacturing focuses on?', 'Single-use products', 'Keeping materials in use longer', 'Increasing waste output', 'Shorter product lifecycles', 'Keeping materials in use longer', 'Circular design emphasizes reuse, repair, and recycling.', 12],
                ['Green hydrogen is produced using?', 'Natural gas without emissions', 'Electrolysis powered by renewable energy', 'Coal-fired power', 'Oil refining', 'Electrolysis powered by renewable energy', 'Green hydrogen uses renewables to split water into hydrogen and oxygen.', 12],
            ],
        ],
        [
            'title' => 'Sustainable Cities Challenge',
            'description' => 'Evaluate strategies that make cities smarter, greener, and livable.',
            'category' => 'Sustainability',
            'difficulty' => 'hard',
            'time_limit' => 480,
            'points_per_question' => 12,
            'pass_percentage' => 80,
            'questions' => [
                ['Transit-oriented development (TOD) encourages?', 'More highway expansion', 'Car-centric planning', 'Compact communities around public transit', 'Avoiding public transport', 'Compact communities around public transit', 'TOD promotes dense, mixed-use neighborhoods near transit.', 12],
                ['Green roofs benefit cities by?', 'Increasing heat islands', 'Causing roof leaks', 'Reducing stormwater runoff and cooling buildings', 'Eliminating urban wildlife', 'Reducing stormwater runoff and cooling buildings', 'Green roofs absorb rainwater and provide insulation.', 12],
                ['The 15-minute city concept means?', 'Driving to work within 15 minutes', 'Residents can access daily needs within a short walk or bike ride', 'All streets are limited to 15 mph', 'City blocks are 15 minutes long', 'Residents can access daily needs within a short walk or bike ride', '15-minute cities reduce reliance on cars by clustering services.', 12],
                ['District energy systems improve efficiency by?', 'Using individual heaters for each home', 'Sharing heating/cooling across multiple buildings', 'Relying solely on diesel generators', 'Lowering energy efficiency', 'Sharing heating/cooling across multiple buildings', 'District systems distribute thermal energy efficiently.', 12],
                ['Smart mobility solutions include?', 'Paper subway tickets only', 'Ride-sharing, bike-sharing, and real-time transit data', 'More parking lots', 'Banning public transport apps', 'Ride-sharing, bike-sharing, and real-time transit data', 'Smart mobility integrates technology to enhance transportation.', 12],
            ],
        ],
    ];

    $created = 0;
    foreach ($quizzes as $quiz) {
        // Check duplicate by title
        $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE title = ?");
        $stmt->execute([$quiz['title']]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            echo "⚠️ Quiz already exists: <strong>{$quiz['title']}</strong><br>";
            continue;
        }

        $categoryId = $categoryNameToId[$quiz['category']] ?? null;
        if (!$categoryId) {
            $categoryId = $getCategoryId($quiz['category'], $quiz['category'] . ' resources', 'fas fa-book', '#198754');
            $categoryNameToId[$quiz['category']] = $categoryId;
        }

        $stmt = $pdo->prepare("INSERT INTO quizzes (title, description, category_id, difficulty_level, time_limit, total_questions, points_per_question, pass_percentage, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $questionCount = count($quiz['questions']);
        $stmt->execute([
            $quiz['title'],
            $quiz['description'],
            $categoryId,
            $quiz['difficulty'],
            $quiz['time_limit'],
            $questionCount,
            $quiz['points_per_question'],
            $quiz['pass_percentage'],
            null,
        ]);
        $quizId = (int)$pdo->lastInsertId();

        $questionStmt = $pdo->prepare("INSERT INTO quiz_questions (quiz_id, question, question_type, option_a, option_b, option_c, option_d, correct_answer, explanation, points, order_index) VALUES (?, ?, 'multiple_choice', ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($quiz['questions'] as $index => $questionData) {
            [$questionText, $optA, $optB, $optC, $optD, $correct, $explanation, $points] = $questionData;
            $questionStmt->execute([
                $quizId,
                $questionText,
                $optA,
                $optB,
                $optC,
                $optD,
                $correct,
                $explanation,
                $points,
                $index + 1,
            ]);
        }

        $created++;
        echo "✅ Added quiz: <strong>{$quiz['title']}</strong> ({$questionCount} questions)<br>";
    }

    if ($created === 0) {
        echo "<p>ℹ️ No new quizzes were added. They may already exist.</p>";
    } else {
        echo "<p>🎉 Successfully created {$created} quizzes with full question sets!</p>";
    }

    echo "<hr><a href='admin/quizzes.php' class='btn btn-success'>Go to Quiz Management</a> ";
    echo "<a href='quiz.php?id=1' class='btn btn-primary'>Preview Sample Quiz</a>";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>

<style>
body { font-family: Arial, sans-serif; background: #f8f9fa; padding: 20px; }
h2 { color: #198754; }
a.btn { display: inline-block; margin: 10px 10px 0 0; padding: 10px 18px; text-decoration: none; border-radius: 6px; color: #fff; }
.btn-success { background: #28a745; }
.btn-primary { background: #0d6efd; }
</style>
