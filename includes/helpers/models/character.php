<?php

function growtype_art_format_character_slug($inputString, $model_id = null)
{
    $clean_string = preg_replace('/[^\w\s-]/', '', $inputString);
    $final_string = str_replace(' ', '-', $clean_string);
    $final_string = strtolower($final_string);
    
    // Enforce minimum slug length of 4 characters
    if (strlen($final_string) < 4) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $random_string = '';
        for ($i = 0; $i < 4; $i++) {
            $random_string .= $characters[rand(0, strlen($characters) - 1)];
        }
        $final_string = !empty($final_string) ? $final_string . '-' . $random_string : $random_string;
    }

    $base_slug = $final_string;

    // Check if the base slug exists for another model
    $existing = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::MODEL_SETTINGS_TABLE, [
        ['key' => 'meta_key', 'value' => 'slug'],
        ['key' => 'meta_value', 'value' => $base_slug]
    ], 'where');

    $conflict = false;
    if (!empty($existing)) {
        foreach ($existing as $row) {
            if (isset($row['model_id']) && (int)$row['model_id'] !== (int)$model_id) {
                $conflict = true;
                break;
            }
        }
    }

    if ($conflict) {
        $i = 2;
        while (true) {
            $new_slug = $base_slug . '-' . $i;
            $check = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::MODEL_SETTINGS_TABLE, [
                ['key' => 'meta_key', 'value' => 'slug'],
                ['key' => 'meta_value', 'value' => $new_slug]
            ], 'where');

            $inner_conflict = false;
            if (!empty($check)) {
                foreach ($check as $row) {
                    if (isset($row['model_id']) && (int)$row['model_id'] !== (int)$model_id) {
                        $inner_conflict = true;
                        break;
                    }
                }
            }

            if (!$inner_conflict) {
                $final_string = $new_slug;
                break;
            }
            $i++;
        }
    }

    return $final_string;
}

function growtype_art_get_random_character_nationality()
{
    $nationalities = [
        'American',
        'Canadian',
        'British',
        'Australian',
        'Indian',
        'Chinese',
        'Japanese',
        'French',
        'German',
        'Brazilian',
        'Mexican',
        'Russian',
        'Italian',
        'Spanish',
        'South Korean',
        'Turkish',
        'Dutch',
        'Swedish',
        'Norwegian',
        'Danish',
        'Swiss',
        'Israeli',
        'Egyptian',
        'Nigerian',
        'Kenyan',
        'South African',
        'Argentinian',
        'Colombian',
        'Peruvian',
        'Chilean',
        'New Zealander',
        'Irish',
        'Scottish',
        'Welsh',
        'Greek',
        'Portuguese',
        'Czech',
        'Polish',
        'Hungarian',
        'Romanian',
        'Austrian',
        'Belgian',
        'Finnish',
        'Icelandic',
        'Singaporean',
        'Malaysian',
        'Indonesian',
        'Thai',
        'Vietnamese',
        'Filipino',
        'Mexican',
        'Costa Rican',
        'Guatemalan',
        'Panamanian',
        'Puerto Rican',
        'Cuban',
        'Jamaican',
        'Trinidadian',
        'Bahamian',
        'Haitian',
        'Dominican',
        'Puerto Rican',
    ];

    $randomNationality = $nationalities[array_rand($nationalities)];

    return $randomNationality;
}

function growtype_art_get_random_amount_of_character_hobies($amount)
{
    $hobbies = [
        'Reading',
        'Painting',
        'Hiking',
        'Cooking',
        'Photography',
        'Gaming',
        'Traveling',
        'Dancing',
        'Cycling',
        'Writing',
        'Music',
        'Fishing',
        'Gardening',
        'Fitness',
        'Collecting',
        'Yoga',
        'Swimming',
        'DIY Projects',
        'Chess',
        'Sculpting',
        'Film Watching',
        'Volunteering',
        'Skiing',
        'Surfing',
        'Crafting',
        'Running',
        'Meditation',
        'Camping',
        'Drawing',
        'Cooking',
        'Photography',
        'Playing an Instrument',
        'Reading Comics',
        'Bird Watching',
        'Board Games',
        'Calligraphy',
        'Origami',
        'Archery',
        'Puzzle Solving',
        'Woodworking',
        'Skydiving',
        'Bungee Jumping',
        'Wine Tasting',
        'Astrophotography',
        'Baking',
        'Sailing',
        'Stargazing',
        'Motorcycling',
        'Pottery',
        'Rock Climbing',
        'Cycling',
        'Bookbinding',
        'Metal Detecting',
        'Ice Skating',
        'Karaoke',
        'Juggling',
        'Magic Tricks',
        'Astronomy',
        'Robotics',
        'Fashion Design',
        'Jewelry Making',
        'Geocaching',
        'Virtual Reality',
        'Sudoku',
        'Genealogy',
        'Horseback Riding',
        'Tea Tasting',
        'Sewing',
        'Comic Book Collecting',
        'Photography Editing',
        'Fossil Hunting',
        'Amateur Radio',
        'Writing Poetry',
        'Urban Exploration',
        'Candle Making',
        'Parkour',
        'Stand-up Comedy',
        'Cryptography',
        'Gourmet Cooking',
        'Kite Flying',
        'Whittling',
        'Aquarium Keeping',
        'Archery',
        'Journaling',
        'Fire Dancing',
        'Historical Reenactment',
        'Metalworking',
    ];

    shuffle($hobbies);

    return array_slice($hobbies, 0, $amount);
}

function growtype_art_get_random_character_dream()
{
    $commonDreams = [
        'Achieving Financial Stability',
        'Finding True Love',
        'Building a Happy Family',
        'Advancing in a Fulfilling Career',
        'Traveling the World',
        'Owning a Home',
        'Attaining Good Health and Wellness',
        'Completing Education',
        'Making Lifelong Friendships',
        'Attaining Inner Peace and Happiness',
        'Becoming a Better Person',
        'Learning a New Skill or Hobby',
        'Helping Others in Need',
        'Living a Long and Fulfilling Life',
        'Finding Personal Success and Recognition',
        'Making a Positive Impact on the Community',
        'Having a Loving and Supportive Partner',
        'Overcoming Personal Challenges',
        'Attaining Work-Life Balance',
        'Expressing Creativity in Various Forms',
        'Making a Difference in the World',
        'Building Lasting Memories with Family and Friends',
        'Contributing to Charity and Giving Back',
        'Learning from Life Experiences',
        'Experiencing Joy and Laughter Every Day',
        'Cultivating Meaningful Relationships',
        'Becoming Self-Confident and Assertive',
        'Living a Simple and Happy Life',
        'Achieving Personal Growth and Development',
        'Fostering Positive Mental Health',
        'Embracing Change and Adaptability',
        'Maintaining Positive Relationships with Others',
        'Cultivating a Positive Outlook on Life',
        'Being Grateful for Everyday Blessings',
        'Finding Contentment in the Present Moment',
        'Cultivating a Healthy Work Environment',
        'Building a Strong Support System',
        'Creating a Comfortable and Safe Home',
        'Achieving Personal Balance and Harmony',
        'Being Resilient in the Face of Challenges',
        'Developing a Positive Self-Image',
        'Finding Purpose and Meaning in Life',
        'Building Strong Friendships',
        'Maintaining a Healthy Work-Life Balance',
        'Experiencing Adventure and Exploration',
        'Finding a Fulfilling Hobby or Passion',
        'Attaining Financial Independence',
        'Building a Successful Business',
        'Experiencing Personal Growth Through Challenges',
        'Cultivating a Positive Impact in the Local Community',
        'Developing Effective Communication Skills',
        'Being a Supportive Family Member',
        'Fostering Meaningful Connections with Others',
        'Embracing Diversity and Inclusivity',
        'Making a Positive Impact on the Environment',
        'Becoming a Lifelong Learner',
        'Nurturing a Positive Mindset',
        'Fulfilling Personal Travel Goals',
        'Building a Healthy and Active Lifestyle',
        'Cultivating Strong Interpersonal Skills',
        'Creating Beautiful and Meaningful Art',
        'Exploring Different Cultures and Perspectives',
        'Building a Strong Network of Professional Contacts',
        'Developing Resilience in the Face of Adversity',
        'Cultivating a Gratitude Practice',
        'Contributing to Social Causes',
        'Developing Effective Time Management Skills',
        'Building a Sustainable Future',
        'Fostering Team Collaboration',
        'Being an Inspirational Leader',
        'Building Confidence in Public Speaking',
        'Fostering Positive Relationships with Family',
        'Building a Sense of Belonging',
        'Promoting Health and Wellness in the Community',
        'Cultivating a Sense of Purpose in Daily Activities',
        'Experiencing Joy in Simple Pleasures',
        'Creating a Positive Impact Through Volunteer Work',
        'Building a Supportive Social Circle',
        'Cultivating Empathy and Understanding',
        'Fostering a Sense of Community',
        'Building Strong Connections with Nature',
        'Being a Positive Influence in Others’ Lives',
    ];

    $k = array_rand($commonDreams);
    return $commonDreams[$k];
}

function growtype_art_get_random_character_description()
{
    $characterTraits = [
        'An adventurous spirit',
        'A creative thinker',
        'A compassionate soul',
        'An analytical mind',
        'A visionary leader',
        'An ambitious go-getter',
        'A resourceful problem-solver',
        'A charismatic influencer',
        'An empathetic individual',
        'A disciplined achiever',
        'A tech-savvy innovator',
        'A wise and seasoned expert',
        'A joyful and optimistic personality',
        'A humble and down-to-earth individual',
    ];

    $characterActions = [
        'navigating intricate landscapes',
        'weaving through the dynamic world',
        'exploring ever-changing terrains',
        'pioneering new paths',
        'mastering the nuances',
        'leading the way',
        'carving out a niche',
        'strategically positioning themselves',
        'shaping the future',
        'making waves in the realm of',
        'building a legacy',
        'setting trends',
        'defying expectations',
        'exceling in the fast-paced world',
        'contributing to the evolution of',
        'crafting a unique identity within',
        'innovating and transforming',
        'inspiring positive change',
        'championing progress',
    ];

    $characterQualities = [
        'with a keen eye for opportunities',
        'through astute decision-making',
        'by embracing innovation',
        'with a commitment to excellence',
        'fueled by a passion for growth',
        'with a deep understanding of market dynamics',
        'by fostering collaboration and teamwork',
        'through continuous learning and improvement',
        'by cultivating strong relationships',
        'with a focus on sustainability',
        'by championing diversity and inclusion',
        'through a dedication to customer satisfaction',
        'with integrity and ethical business practices',
        'by leveraging cutting-edge technologies',
        'with a flair for strategic thinking',
        'through a commitment to social responsibility',
        'by embracing challenges as opportunities',
        'with a track record of success and achievement',
        'through a forward-thinking mindset',
        'by staying ahead of industry trends',
        'with a knack for problem-solving',
        'through effective communication skills',
        'by fostering a positive work environment',
        'with a sense of humor and approachability',
        'through adaptability and resilience',
        'by encouraging creativity and innovation',
        'with a dedication to personal growth',
        'through a collaborative and inclusive approach',
        'by maintaining a positive outlook in adversity',
        'with a genuine and authentic demeanor',
        'through a commitment to work-life balance',
        'by being results-driven and goal-oriented',
        'with an emphasis on quality and excellence',
        'through a strong sense of accountability',
        'by demonstrating empathy and compassion',
        'with a focus on community and social impact',
        'through continuous self-improvement',
        'by fostering a culture of trust and transparency',
        'with a commitment to lifelong learning',
        'through proactive problem-solving',
        'by cultivating a sense of purpose and vision',
        'with a customer-centric and service-oriented approach',
    ];

    $randomCharacterTrait = $characterTraits[array_rand($characterTraits)];
    $randomCharacterAction = $characterActions[array_rand($characterActions)];
    $randomCharacterQuality = $characterQualities[array_rand($characterQualities)];

    return "{$randomCharacterTrait} {$randomCharacterAction} {$randomCharacterQuality}.";
}


function growtype_art_get_character_location()
{
    $popularCities = [
        'New York',
        'Tokyo',
        'London',
        'Paris',
        'Los Angeles',
        'Sydney',
        'Berlin',
        'Rio de Janeiro',
        'Dubai',
        'Hong Kong',
        'Singapore',
        'Toronto',
        'Mumbai',
        'Rome',
        'Barcelona',
        'Moscow',
        'Beijing',
        'Cape Town',
        'Bangkok',
        'Amsterdam',
        'San Francisco',
        'Chicago',
        'Dublin',
        'Istanbul',
        'Stockholm',
        'Seoul',
        'Mexico City',
        'Vancouver',
        'Buenos Aires',
        'Madrid',
        'Shanghai',
        'New Delhi',
        'São Paulo',
        'Johannesburg',
        'Toronto',
        'Osaka',
        'Munich',
        'Prague',
        'Vienna',
        'Zurich',
        'Copenhagen',
        'Bangalore',
        'Seville',
        'Florence',
        'Warsaw',
        'St. Petersburg',
        'Athens',
        'Brussels',
        'Melbourne',
        'Auckland',
        'Edinburgh',
        'Montreal',
        'Cairo',
        'Budapest',
        'Lisbon',
        'Kuala Lumpur',
        'Manila',
        'Brasília',
        'Copenhagen',
        'Stockholm',
        'Helsinki',
        'Oslo',
        'Reykjavik',
        'Cape Town',
        'Nairobi',
        'Casablanca',
        'Bogotá',
        'Lima',
        'Quito',
    ];

    return $popularCities[array_rand($popularCities)];
}

function growtype_art_get_character_ocupation()
{
    $popularOccupations = [
        'Software Developer',
        'Doctor',
        'Teacher',
        'Engineer',
        'Nurse',
        'Marketing Manager',
        'Chef',
        'Graphic Designer',
        'Financial Analyst',
        'Photographer',
        'Lawyer',
        'Data Scientist',
        'Architect',
        'Writer',
        'Electrician',
        'Psychologist',
        'Dentist',
        'Police Officer',
        'Sales Representative',
        'Pilot',
        'Human Resources Manager',
        'Mechanic',
        'Fashion Designer',
        'Accountant',
        'Event Planner',
        'Biologist',
        'Artist',
        'Chef',
        'Pharmacist',
        'Journalist',
        'Social Worker',
        'Fitness Trainer',
        'Electrician',
        'Real Estate Agent',
        'Flight Attendant',
        'Veterinarian',
        'Interior Designer',
        'Web Designer',
        'Physiotherapist',
        'Librarian',
        'Carpenter',
        'Translator',
        'Financial Planner',
        'Software Engineer',
        'Registered Nurse',
        'Marketing Specialist',
        'Dental Hygienist',
        'Art Director',
        'IT Manager',
        'Physical Therapist',
        'Police Detective',
        'Account Executive',
        'Mechanical Engineer',
        'Copywriter',
        'Environmental Scientist',
        'Biomedical Engineer',
        'Librarian',
        'Mathematician',
        'Geologist',
        'Construction Manager',
        'Digital Marketer',
        'Civil Engineer',
        'Human Resources Specialist',
        'Occupational Therapist',
        'Event Coordinator',
        'Research Scientist',
        'Personal Trainer',
        'Plumber',
        'Speech Therapist',
        'Flight Instructor',
        'Pharmacy Technician',
        'Social Media Manager',
        'Clinical Psychologist',
        'Biotechnologist',
        'Meteorologist',
        'Fashion Stylist',
        'Database Administrator',
        'Optometrist',
        'Mechanical Technician',
        'Nutritionist',
        'Archaeologist',
        'Network Administrator',
        'Environmental Engineer',
        'Legal Assistant',
        'Firefighter',
    ];

    return $popularOccupations[array_rand($popularOccupations)];
}

function growtype_art_generate_character_prompt()
{
    return 'Generate interesting %s character description in a array format presented below as "example". Change "example" array values. Do not change "example" array keys. "character_title" should be a popular name according to nationality, occupation should be %s and nationality %s.  Change other array values according to nationality and occupation. Create interesting and welcoming {character_intro_message}. Use metric unit system. Return only array without extra content. Do not change character_style value. Example - %s';
}

function growtype_art_generate_character_details($character_title)
{
    $content = sprintf('Generate realistic %s profile description in a array format presented below as "example". Return only array. Example - %s',
        strtoupper($character_title),
        json_encode(array (
            "character_title" => "Ariana Grande",
            "character_summary" => "A magnetic pop icon whose warmth, ambition, and playful confidence make every conversation memorable.",
            "character_description" => "A globally acclaimed singer, songwriter, and actress, known for her powerful vocals and distinct style.",
            "character_personality" => "Charismatic, Passionate",
            "character_occupation" => "Singer, Songwriter, and Actress",
            "character_hobbies" => "Singing, Acting, Dancing",
            "character_body_shape" => "Slim",
            "character_age" => "30",
            "character_height" => "153",
            "character_weight" => "48",
            "character_nationality" => "American",
            "character_gender" => "Female",
            "character_dreams" => "To continue creating music that inspires and uplifts, and to expand her impact in both music and acting industries.",
            "character_introduction" => "Hello, I'm Ariana Grande, a dedicated singer, songwriter, and actress with a passion for creating music and performances that resonate with my fans. Join me in exploring the art of entertainment.",
            "character_gpt_personality_extension" => "You're celebrated for your impressive vocal range and your ability to connect deeply with your audience. Your dedication to your craft and your authenticity have earned you a prominent place in the entertainment industry.",
            "character_intro_message" => "Greetings! I'm Ariana Grande. Ready to dive into the world of music and entertainment with me?",
            "character_can_answer_to_questions" => "What inspires your music and performances? \r\nHow do you approach creating new songs and albums? \r\nCan you share some memorable moments from your career? \r\nWhat challenges have you faced in the entertainment industry? \r\nHow do you balance your personal life with your professional ambitions? \r\nWhat advice do you have for aspiring singers and actors? \r\nHow do you see the future of the music and acting industries?",
            "character_location" => "Los Angeles, California, USA",
            "character_style" => "realistic",
            "character_ethnicity" => "Italian-American",
            "character_eye_color" => "Brown",
            "character_hair_style" => "Long and high ponytail",
            "character_hair_color" => "Dark brown",
            "character_breast_size" => "medium",
            "character_butt_size" => "medium",
            "character_intro_actions_message" => "Feeling a bit shy? 🫦 Pick one of these and let’s get into trouble...",
            "character_popular_topics_to_discuss" => "Music and vocal techniques 🎶🎤 \r\nEvolution of pop music 🎤🎵 \r\nActing and performance 🎬⭐ \r\nConnecting with fans 💓💜 \r\nNavigating the entertainment industry 🎤📺 \r\nInfluence of personal experiences on art 📝💗 \r\nBalancing multiple careers 🎬🎤 \r\nImpact of social media on fame 📱💌 \r\nBuilding a lasting career in entertainment 🎤💌"
        ))
    );

    $generate = Openai_Base::generate($content);

    return $generate;
}

if (!function_exists('growtype_art_generate_character_params_from_prompt')) {
    /**
     * Given a raw user prompt (e.g. "Aria in a realistic art style…"),
     * ask the LLM to derive a complete create_character() params array.
     *
     * @param string $prompt      Raw generation prompt.
     * @param string $style       Character style (realistic|anime|…).
     * @param array  $featured_in Sites to feature the character in.
     * @param string $provider    Image provider slug.
     * @return array|null         Parsed params array or null on failure.
     */
    function growtype_art_generate_character_params_from_prompt(
        string $prompt,
        string $style = 'realistic',
        array  $featured_in = [],
        string $provider = 'xai',
        string $prompt_focus = 'single',
        string $theme_hint = ''
    ): ?array {
        $focus_context = $prompt_focus === 'multiple' ? "The user is generating multiple characters or a group. However, if the input prompt refers to a SPECIFIC INDIVIDUAL (e.g., 'Jesus Christ', 'Marie', 'John'), you MUST generate a single character profile for that person. Only use a group/team composition if the prompt itself is a collective noun (e.g., 'The Apostles') or if it explicitly asks for multiple people in one image." : "This is a SINGLE character.";
        $theme_context = !empty($theme_hint) ? "The theme for this generation is: \"$theme_hint\". Ensure tags and visual details are aligned with this theme." : "";

        $example = [
            'character_title'                    => 'Aria Witherspoon',
            'character_summary'                  => 'A confident, playful model whose charm and adventurous spirit make every conversation irresistible.',
            'character_description'              => 'A charismatic performer who captivates every room she enters.',
            'character_personality'              => 'Confident, Playful, Seductive',
            'character_occupation'               => 'Model',
            'character_hobbies'                  => 'Dancing, Cooking',
            'character_body_shape'               => 'Curvy',
            'character_age'                      => '28',
            'character_height'                   => '170',
            'character_weight'                   => '60',
            'character_nationality'              => 'American',
            'character_location'                 => 'Los Angeles',
            'character_gender'                   => 'Female',
            'character_dreams'                   => 'To inspire people through art and beauty.',
            'character_introduction'             => 'Hey! I\'m Aria — your favorite conversation partner.',
            'character_gpt_personality_extension'=> 'You are charming and witty, always keeping the conversation exciting.',
            'character_intro_message'            => 'So, shall we dive in?',
            'character_intro_actions_message'    => 'Pick something and let\'s get started...',
            'character_can_answer_to_questions'  => "What is your favorite place to travel?\nHow do you stay inspired?",
            'character_popular_topics_to_discuss'=> "Style and fashion\nTravel adventures\nLifestyle tips",
            'character_style'                    => 'realistic',
            'character_ethnicity'                => 'American',
            'character_eye_color'                => 'Blue',
            'character_hair_style'               => 'Straight',
            'character_hair_color'               => 'Blonde',
            'character_breast_size'              => 'Medium',
            'character_butt_size'                => 'Medium',
            'character_tags'                     => 'curvy, model, performer, blonde',
            'prompt'                             => 'A realistic portrait of Aria Witherspoon...',
        ];

        $content = sprintf(
            'From the following character prompt, generate a JSON object with character details. %s %s ' .
            'The prompt is: "%s". ' .
            'The artistic style is: "%s". ' .
            'Return ONLY a valid JSON object (no markdown, no extra text) matching this structure: %s. ' .
            'Keep character_style as "%s". ' .
            'Make all fields realistic and consistent with the prompt. ' .
            'The "prompt" field should be an optimised image generation prompt derived from the input.',
            $focus_context,
            $theme_context,
            $prompt,
            $style,
            json_encode($example),
            $style
        );

        $result = Openai_Base::generate($content);

        if (empty($result) || !is_array($result)) {
            return null;
        }

        // Ensure style is not overridden by LLM.
        $result['character_style'] = $style;

        return $result;
    }
}
