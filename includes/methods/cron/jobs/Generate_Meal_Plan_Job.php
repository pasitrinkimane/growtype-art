<?php

use Orhanerday\OpenAi\OpenAi;

class Generate_Meal_Plan_Job
{
    public function run($job)
    {
        $job_payload = json_decode($job['payload'], true);

        $user_details = $job_payload['user_details'] ?? '';
        $day_name = $job_payload['day_name'] ?? '';

        if (empty($user_details)) {
            return '';
        }

        $meal_plan_details = $this->create_meal_plan($user_details, $day_name);
        $meal_plan = $meal_plan_details['plan'] ?? '';
        $meal_plan_day = $meal_plan_details['day'] ?? '';

//        $meal_plan = '{"lunch":{"name":"Grilled Chicken Salad","ingredients":{"Grilled chicken breast":{"unit":"grams","value":150},"Mixed greens":{"unit":"grams","value":100},"Cherry tomatoes":{"unit":"grams","value":50},"Cucumber (sliced)":{"unit":"grams","value":30},"Avocado (sliced)":{"unit":"grams","value":50},"Olive oil":{"unit":"ml","value":10},"Balsamic vinegar":{"unit":"ml","value":10},"Salt and pepper":{"unit":"","value":"To taste"}},"recipe":{"text":"Combine %s of mixed greens, %s of cherry tomatoes, %s of sliced cucumber, and %s of sliced avocado. Top with %s of grilled chicken breast slices, drizzle with %s of olive oil and %s of balsamic vinegar. Season with salt and pepper to taste. Toss gently before serving.","units":[{"unit":"grams","value":100},{"unit":"grams","value":50},{"unit":"grams","value":30},{"unit":"grams","value":50},{"unit":"grams","value":150},{"unit":"ml","value":10},{"unit":"ml","value":10}]},"duration":{"unit":"minutes","value":20},"macronutrients":{"Carbohydrates":{"unit":"grams","value":15},"Protein":{"unit":"grams","value":25},"Fat":{"unit":"grams","value":20}},"calories":350,"dietary_info":{"Vegan":false,"Vegetarian":false,"Gluten Intolerant":true,"Glucose Intolerant":false,"Diabetic":true,"Diarrhea":true,"Constipation":true},"benefits":["High in protein (chicken)","Rich in fiber (vegetables)","Healthy fats (avocado and olive oil)","Provides essential vitamins and minerals"]},"afternoon_snack":{"name":"Greek Yogurt with Mixed Berries","ingredients":{"Greek yogurt":{"unit":"grams","value":150},"Mixed berries":{"unit":"grams","value":100}},"duration":{"unit":"minutes","value":10},"macronutrients":{"Carbohydrates":{"unit":"grams","value":20},"Protein":{"unit":"grams","value":15},"Fat":{"unit":"grams","value":5}},"calories":180,"dietary_info":{"Vegan":false,"Vegetarian":true,"Gluten Intolerant":true,"Glucose Intolerant":false,"Diabetic":true,"Diarrhea":true,"Constipation":true},"benefits":["High in protein (Greek yogurt)","Rich in antioxidants (berries)","Probiotics for gut health","Low in added sugars"]},"dinner":{"name":"Salmon with Quinoa and Steamed Broccoli","ingredients":{"Salmon fillet":{"unit":"grams","value":150},"Quinoa (cooked)":{"unit":"grams","value":100},"Broccoli florets":{"unit":"grams","value":100},"Lemon wedges":{"unit":"units","value":1},"Olive oil":{"unit":"ml","value":10},"Garlic (minced)":{"unit":"teaspoon","value":1},"Salt and pepper":{"unit":"","value":"To taste"}},"recipe":{"text":"Season %s of salmon fillet with salt and pepper. In a pan, heat %s of olive oil and sauté %s of minced garlic. Add the seasoned salmon fillet and cook until done. Serve over %s of cooked quinoa and %s of steamed broccoli. Garnish with %s of lemon wedges.","units":[{"unit":"grams","value":150},{"unit":"ml","value":10},{"unit":"teaspoon","value":1},{"unit":"grams","value":100},{"unit":"grams","value":100},{"unit":"units","value":1}]},"duration":{"unit":"minutes","value":25},"macronutrients":{"Carbohydrates":{"unit":"grams","value":30},"Protein":{"unit":"grams","value":35},"Fat":{"unit":"grams","value":15}},"calories":400,"dietary_info":{"Vegan":false,"Vegetarian":false,"Gluten Intolerant":true,"Glucose Intolerant":false,"Diabetic":true,"Diarrhea":true,"Constipation":true},"benefits":["High in omega-3 fatty acids (salmon)","Complete protein source (quinoa)","Rich in fiber (broccoli)","Healthy fats (olive oil)"]},"evening_snack":{"name":"Carrot Sticks with Hummus","ingredients":{"Carrot sticks":{"unit":"grams","value":100},"Hummus":{"unit":"grams","value":50}},"duration":{"unit":"minutes","value":10},"macronutrients":{"Carbohydrates":{"unit":"grams","value":15},"Protein":{"unit":"grams","value":5},"Fat":{"unit":"grams","value":8}},"calories":120,"dietary_info":{"Vegan":true,"Vegetarian":true,"Gluten Intolerant":true,"Glucose Intolerant":true},"benefits":["Rich in beta-carotene (carrots)","Good source of plant-based protein (hummus)","Healthy fats from hummus","High in fiber"]}}';
//        $meal_plan_day = 'Monday';


        if (!empty($meal_plan)) {
            $meal_plan = json_decode($meal_plan, true);

            if (empty($meal_plan)) {
                $meal_plan = Openai_Base_Meal::fix_malformed_json($meal_plan);

                if (!empty($meal_plan)) {
                    $meal_plan = json_decode($meal_plan);

                    /**
                     * Remove last meal if mising dietary info
                     */
                    $meal_plan_last_meal = end($meal_plan);

                    if (!isset($meal_plan_last_meal['dietary_info']) || (isset($meal_plan_last_meal['dietary_info']) && count($meal_plan_last_meal['dietary_info']) < 4)) {
                        array_pop($meal_plan);
                    }
                }
            }

            if (!empty($meal_plan)) {
                global $wpdb;

                $final_meal_plan = [];
                foreach ($meal_plan as $key => $meal) {
                    $term = get_term_by('slug', $key, 'meal_tax');

                    if (empty($term)) {
                        $term_id = wp_insert_term($key, 'meal_tax', array (
                            'slug' => $key,
                        ));
                    } else {
                        $term_id = $term->term_id;
                    }

                    $post_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_content = '" . json_encode($meal) . "' AND post_type='meal'");

                    if (!$post_id) {
                        $post_title_amount = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_title = '" . $meal['name'] . "' AND post_type='meal'");

                        if (empty($post_title_amount)) {
                            $post_title = $meal['name'];
                        } else {
                            $post_title = $meal['name'] . '-' . $post_title_amount;
                        }

                        $new_post = array (
                            'post_type' => 'meal',
                            'post_title' => $post_title,
                            'post_content' => json_encode($meal),
                            'post_status' => 'publish',
                            'post_excerpt' => json_encode($user_details),
                        );

                        $post_id = wp_insert_post($new_post);

                        if (!$post_id) {
                            error_log('Error inserting post' . print_r($meal, true));
                        } else {
                            wp_set_post_terms($post_id, [$term_id], 'meal_tax');
                        }
                    }

                    $final_meal_plan[$key] = (int)$post_id;
                }

                $required_keys = ['breakfast', 'morning_snack', 'lunch', 'afternoon_snack', 'dinner', 'evening_snack'];

                $missing_meals = array_diff($required_keys, array_keys($final_meal_plan));

                foreach ($missing_meals as $meal) {
                    $args = array (
                        'post_type' => 'meal', // Replace with your custom post type if applicable
                        'tax_query' => array (
                            array (
                                'taxonomy' => 'meal_tax', // Replace with the actual taxonomy name
                                'field' => 'slug',
                                'terms' => $meal,
                            ),
                        ),
                    );

                    $posts = get_posts($args);

                    $post = array_shuffle($posts)[0];

                    $final_meal_plan[$meal] = $post->ID;
                }

                $post_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_content = '" . json_encode($final_meal_plan) . "' AND post_type='meal_plan'");

                if (!$post_id) {
                    $new_post = array (
                        'post_type' => 'meal_plan',
                        'post_title' => $meal_plan_day,
                        'post_content' => json_encode($final_meal_plan),
                        'post_status' => 'draft',
                        'post_excerpt' => json_encode($user_details),
                    );

                    wp_insert_post($new_post);
                }

                error_log('MEAL PLAN IMPORTING SUCCESSFUL!!!!');
            } else {
                error_log('MEAL PLAN MALFORMED JSON!!!! ' . print_r($meal_plan_details, true));
            }
        } else {
            error_log('EMPTY MEAL PLAN!' . print_r($meal_plan_details, true));
        }

//        error_log('Meal plan: ' . print_r($completion, true));

//        var_dump($completion);
//        die();
    }

    function create_meal_plan($user_details, $day_name = null)
    {
        extract($user_details);

        $day_name_number = rand(0, 6);

        $days_off_week = array ('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
        $day_name = !empty($day_name) ? $day_name : $days_off_week[$day_name_number];

        $content = sprintf('Create a %s day meal plan for a %s who has a primary goal to %s, who prefers %s diet, who has %s food allergies, who has %s medican conditions, who exercise %s, who would like to exercise %s per week, whose age is %s, whose height is %s cm, whose weight is %s kg and would like to reach %s kg weight.',
            $day_name,
            json_encode($gender),
            json_encode($primary_goal),
            json_encode($dietary_preferences),
            json_encode($food_allergies),
            json_encode($medical_conditions),
            json_encode($exercise_frequency),
            json_encode($weekly_workouts),
            $age,
            $height,
            $current_weight,
            $target_weight
        );

        $content = $content . ' Return only json without additional text.';

        error_log('MEAL PLAN GENERATING STARTED WITH CONTENT: ' . print_r($content, true));

        $open_ai = new OpenAi(Openai_Base::api_key());

        $example_output = self::example_day_meals();

        $complete = $open_ai->chat([
            'model' => 'gpt-3.5-turbo-1106',
            'messages' => [
                [
                    "role" => "system",
                    "content" => 'You are a professional dietitian specializing in crafting straightforward and easily-followed personalized daily meal plans. Each plan consists of breakfast, morning snack, lunch, afternoon snack, dinner, and evening snack. For each meal, you provide the name, ingredients, recipe with units, duration, macronutrients, calories, dietary information, and benefits. All recipes are designed using products readily available in local supermarkets.'
                ],
                [
                    "role" => "user",
                    "content" => 'create a day meal plan for monday, return only json'
                ],
                [
                    "role" => "system",
                    "content" => json_encode($example_output)
                ],
                [
                    "role" => "user",
                    "content" => $content
                ],
//                [
//                    "role" => "system",
//                    "content" => '{"breakfast":{"name":"Greek Yogurt Parfait","ingredients":{"Greek yogurt":{"unit":"grams","value":150},"Granola":{"unit":"grams","value":30},"Mixed berries":{"unit":"grams","value":50},"Honey":{"unit":"ml","value":10}},"recipe":{"text":"In a bowl, layer %s of Greek yogurt with %s of granola and %s of mixed berries. Drizzle with %s of honey.","units":[{"unit":"grams","value":150},{"unit":"grams","value":30},{"unit":"grams","value":50},{"unit":"ml","value":10}]},"duration":{"unit":"minutes","value":10},"macronutrients":{"Carbohydrates":{"unit":"grams","value":30},"Protein":{"unit":"grams","value":15},"Fat":{"unit":"grams","value":5}},"calories":250,"dietary_info":{"Vegan":false,"Vegetarian":true,"Gluten Intolerant":true,"Glucose Intolerant":false,"Diabetic":true,"Diarrhea":true,"Constipation":true},"benefits":["High in protein (Greek yogurt)","Rich in antioxidants (berries)","Provides essential vitamins and minerals"]},"morning_snack":{"name":"Apple Slices with Almond Butter","quantity":{"unit":"grams","value":150},"duration":{"unit":"minutes","value":5},"macronutrients":{"Carbohydrates":{"unit":"grams","value":20},"Protein":{"unit":"grams","value":3},"Fat":{"unit":"grams","value":10}},"calories":150,"dietary_info":{"Vegan":true,"Vegetarian":true,"Gluten Intolerant":true,"Glucose Intolerant":false,"Diabetic":true,"Diarrhea":true,"Constipation":true},"benefits":["Rich in fiber (apple)","Healthy fats from almond butter","Provides essential vitamins and minerals"]},"lunch":{"name":"Quinoa and Black Bean Salad","ingredients":{"Quinoa (cooked)":{"unit":"grams","value":100},"Black beans (cooked)":{"unit":"grams","value":100},"Corn kernels":{"unit":"grams","value":50},"Red bell pepper (diced)":{"unit":"grams","value":50},"Cilantro (chopped)":{"unit":"grams","value":10},"Lime juice":{"unit":"ml","value":10},"Olive oil":{"unit":"ml","value":5},"Salt and pepper":{"unit":"","value":"To taste"}},"recipe":{"text":"In a bowl, combine %s of cooked quinoa, %s of cooked black beans, %s of corn kernels, %s of diced red bell pepper, and %s of chopped cilantro. Drizzle with %s of lime juice and %s of olive oil. Season with salt and pepper to taste.","units":[{"unit":"grams","value":100},{"unit":"grams","value":100},{"unit":"grams","value":50},{"unit":"grams","value":50},{"unit":"grams","value":10},{"unit":"ml","value":10},{"unit":"ml","value":5}]},"duration":{"unit":"minutes","value":15},"macronutrients":{"Carbohydrates":{"unit":"grams","value":35},"Protein":{"unit":"grams","value":15},"Fat":{"unit":"grams","value":10}},"calories":300,"dietary_info":{"Vegan":true,"Vegetarian":true,"Gluten Intolerant":true,"Glucose Intolerant":false,"Diabetic":true,"Diarrhea":true,"Constipation":true},"benefits":["High in protein (quinoa and black beans)","Rich in fiber (vegetables)","Healthy fats from olive oil","Provides essential vitamins and minerals"]},"afternoon_snack":{"name":"Cherry Tomato and Mozzarella Skewers","ingredients":{"Cherry tomatoes":{"unit":"grams","value":100},"Mozzarella balls":{"unit":"grams","value":50},"Basil leaves":{"unit":"grams","value":10},"Balsamic glaze":{"unit":"ml","value":5}},"recipe":{"text":"Thread %s of cherry tomatoes, %s of mozzarella balls, and %s of basil leaves onto skewers. Drizzle with %s of balsamic glaze.","units":[{"unit":"grams","value":100},{"unit":"grams","value":50},{"unit":"grams","value":10},{"unit":"ml","value":5}]},"duration":{"unit":"minutes","value":10},"macronutrients":{"Carbohydrates":{"unit":"grams","value":10},"Protein":{"unit":"grams","value":10},"Fat":{"unit":"grams","value":8}},"calories":150,"dietary_info":{"Vegan":false,"Vegetarian":true,"Gluten Intolerant":true,"Glucose Intolerant":false,"Diabetic":true,"Diarrhea":true,"Constipation":true},"benefits":["Rich in antioxidants (tomatoes)","Good source of calcium (mozzarella)","Provides essential vitamins and minerals"]},"dinner":{"name":"Vegetarian Stir-Fry with Tofu","ingredients":{"Tofu (firm, cubed)":{"unit":"grams","value":150},"Broccoli florets":{"unit":"grams","value":100},"Carrots (sliced)":{"unit":"grams","value":50},"Snap peas":{"unit":"grams","value":50},"Bell peppers (sliced)":{"unit":"grams","value":75},"Soy sauce":{"unit":"ml","value":15},"Sesame oil":{"unit":"ml","value":5},"Garlic (minced)":{"unit":"teaspoon","value":1},"Ginger (minced)":{"unit":"teaspoon","value":1},"Cooked brown rice":{"unit":"grams","value":100}},"recipe":{"text":"In a wok, heat %s of sesame oil and sauté %s of minced garlic and %s of minced ginger. Add %s of cubed tofu, %s of broccoli florets, %s of sliced carrots, %s of snap peas, and %s of sliced bell peppers. Stir in %s of soy sauce and cook until vegetables are tender. Serve over %s of cooked brown rice.","units":[{"unit":"ml","value":5},{"unit":"teaspoon","value":1},{"unit":"teaspoon","value":1},{"unit":"grams","value":150},{"unit":"grams","value":100},{"unit":"grams","value":50},{"unit":"grams","value":50},{"unit":"grams","value":75},{"unit":"ml","value":15},{"unit":"grams","value":100}]},"duration":{"unit":"minutes","value":20},"macronutrients":{"Carbohydrates":{"unit":"grams","value":40},"Protein":{"unit":"grams","value":25},"Fat":{"unit":"grams","value":15}},"calories":400,"dietary_info":{"Vegan":true,"Vegetarian":true,"Gluten Intolerant":true,"Glucose Intolerant":false,"Diabetic":true,"Diarrhea":true,"Constipation":true},"benefits":["High in protein (tofu)","Rich in fiber (vegetables)","Healthy fats from sesame oil","Provides essential vitamins and minerals"]},"evening_snack":{"name":"Mixed Nuts","ingredients":{"Almonds":{"unit":"grams","value":30},"Walnuts":{"unit":"grams","value":30},"Cashews":{"unit":"grams","value":30},"Pistachios":{"unit":"grams","value":30}},"duration":{"unit":"minutes","value":5},"macronutrients":{"Carbohydrates":{"unit":"grams","value":10},"Protein":{"unit":"grams","value":8},"Fat":{"unit":"grams","value":20}},"calories":250,"dietary_info":{"Vegan":false,"Vegetarian":true,"Gluten Intolerant":true,"Glucose Intolerant":true},"benefits":["Rich in healthy fats","Good source of protein","Provides essential vitamins and minerals"]}}'
//                ],
//                [
//                    "role" => "user",
//                    "content" => 'create a day meal plan for wednesday, return only json'
//                ],
//                [
//                    "role" => "system",
//                    "content" => '{"breakfast":{"name":"Chia Seed Pudding with Mixed Berries","ingredients":{"Chia seeds":{"unit":"grams","value":30},"Almond milk":{"unit":"ml","value":250},"Vanilla extract":{"unit":"teaspoon","value":1},"Maple syrup":{"unit":"tablespoon","value":1},"Mixed berries":{"unit":"grams","value":100}},"recipe":{"text":"In a jar, mix %s of chia seeds with %s of almond milk, %s of vanilla extract, and %s of maple syrup. Stir well and refrigerate overnight. Top with %s of mixed berries before serving.","units":[{"unit":"grams","value":30},{"unit":"ml","value":250},{"unit":"teaspoon","value":1},{"unit":"tablespoon","value":1},{"unit":"grams","value":100}]},"duration":{"unit":"minutes","value":10},"macronutrients":{"Carbohydrates":{"unit":"grams","value":30},"Protein":{"unit":"grams","value":5},"Fat":{"unit":"grams","value":10}},"calories":220,"dietary_info":{"Vegan":true,"Vegetarian":true,"Gluten Intolerant":true,"Glucose Intolerant":false,"Diabetic":true,"Diarrhea":true,"Constipation":true},"benefits":["Rich in omega-3 fatty acids (chia seeds)","Dairy-free and plant-based","Antioxidant-rich berries"]},"morning_snack":{"name":"Orange Slices with Dark Chocolate","quantity":{"unit":"grams","value":150},"duration":{"unit":"minutes","value":5},"macronutrients":{"Carbohydrates":{"unit":"grams","value":20},"Protein":{"unit":"grams","value":2},"Fat":{"unit":"grams","value":5}},"calories":120,"dietary_info":{"Vegan":true,"Vegetarian":true,"Gluten Intolerant":true,"Glucose Intolerant":true},"benefits":["High in vitamin C (oranges)","Dark chocolate provides antioxidants","Provides essential vitamins and minerals"]},"lunch":{"name":"Quinoa and Vegetable Stuffed Peppers","ingredients":{"Bell peppers":{"unit":"units","value":2},"Quinoa (cooked)":{"unit":"grams","value":100},"Black beans (canned, drained)":{"unit":"grams","value":50},"Corn kernels":{"unit":"grams","value":50},"Tomatoes (diced)":{"unit":"grams","value":50},"Avocado (diced)":{"unit":"grams","value":50},"Cilantro (chopped)":{"unit":"grams","value":10},"Lime juice":{"unit":"ml","value":10},"Cumin powder":{"unit":"teaspoon","value":1},"Salt and pepper":{"unit":"","value":"To taste"}},"recipe":{"text":"Preheat the oven. Cut %s in half and remove seeds. In a bowl, mix %s of cooked quinoa, %s of black beans, %s of corn kernels, %s of diced tomatoes, %s of diced avocado, %s of chopped cilantro, %s of lime juice, %s of cumin powder, salt, and pepper. Stuff the peppers with the mixture. Bake until peppers are tender.","units":[{"unit":"units","value":2},{"unit":"grams","value":100},{"unit":"grams","value":50},{"unit":"grams","value":50},{"unit":"grams","value":50},{"unit":"grams","value":50},{"unit":"grams","value":10},{"unit":"ml","value":10},{"unit":"teaspoon","value":1}]},"duration":{"unit":"minutes","value":30},"macronutrients":{"Carbohydrates":{"unit":"grams","value":40},"Protein":{"unit":"grams","value":10},"Fat":{"unit":"grams","value":15}},"calories":350,"dietary_info":{"Vegan":true,"Vegetarian":true,"Gluten Intolerant":true,"Glucose Intolerant":false,"Diabetic":true,"Diarrhea":true,"Constipation":true},"benefits":["High in protein (quinoa and black beans)","Rich in fiber (vegetables)","Healthy fats from avocado","Provides essential vitamins and minerals"]},"afternoon_snack":{"name":"Green Smoothie","ingredients":{"Spinach":{"unit":"grams","value":50},"Banana":{"unit":"units","value":1},"Pineapple chunks":{"unit":"grams","value":50},"Chia seeds":{"unit":"grams","value":15},"Coconut water":{"unit":"ml","value":250}},"recipe":{"text":"In a blender, combine %s of spinach, %s of banana, %s of pineapple chunks, %s of chia seeds, and %s of coconut water. Blend until smooth.","units":[{"unit":"grams","value":50},{"unit":"units","value":1},{"unit":"grams","value":50},{"unit":"grams","value":15},{"unit":"ml","value":250}]},"duration":{"unit":"minutes","value":5},"macronutrients":{"Carbohydrates":{"unit":"grams","value":40},"Protein":{"unit":"grams","value":5},"Fat":{"unit":"grams","value":5}},"calories":200,"dietary_info":{"Vegan":true,"Vegetarian":true,"Gluten Intolerant":true,"Glucose Intolerant":false,"Diabetic":true,"Diarrhea":true,"Constipation":true},"benefits":["Rich in vitamins and minerals (spinach)","Natural energy boost from bananas","Hydrating coconut water"]},"dinner":{"name":"Baked Salmon with Asparagus","ingredients":{"Salmon fillet":{"unit":"grams","value":150},"Asparagus spears":{"unit":"grams","value":100},"Lemon wedges":{"unit":"units","value":1},"Olive oil":{"unit":"ml","value":10},"Garlic (minced)":{"unit":"teaspoon","value":1},"Dill (chopped)":{"unit":"teaspoon","value":1},"Salt and pepper":{"unit":"","value":"To taste"}},"recipe":{"text":"Preheat the oven. Place %s of salmon fillet and %s of asparagus spears on a baking sheet. Drizzle with %s of olive oil, %s of minced garlic, %s of chopped dill, and season with salt and pepper. Bake until salmon is cooked through and asparagus is tender. Serve with %s of lemon wedges.","units":[{"unit":"grams","value":150},{"unit":"grams","value":100},{"unit":"ml","value":10},{"unit":"teaspoon","value":1},{"unit":"teaspoon","value":1},{"unit":"units","value":1}]},"duration":{"unit":"minutes","value":25},"macronutrients":{"Carbohydrates":{"unit":"grams","value":10},"Protein":{"unit":"grams","value":30},"Fat":{"unit":"grams","value":15}},"calories":350,"dietary_info":{"Vegan":false,"Vegetarian":false,"Gluten Intolerant":true,"Glucose Intolerant":false,"Diabetic":true,"Diarrhea":true,"Constipation":true},"benefits":["High in omega-3 fatty acids (salmon)","Rich in fiber (asparagus)","Healthy fats from olive oil","Provides essential vitamins and minerals"]},"evening_snack":{"name":"Cucumber Slices with Guacamole","ingredients":{"Cucumber slices":{"unit":"grams","value":100},"Guacamole":{"unit":"grams","value":50}},"duration":{"unit":"minutes","value":10},"macronutrients":{"Carbohydrates":{"unit":"grams","value":10},"Protein":{"unit":"grams","value":3},"Fat":{"unit":"grams","value":10}},"calories":150,"dietary_info":{"Vegan":true,"Vegetarian":true,"Gluten Intolerant":true,"Glucose Intolerant":true},"benefits":["Hydrating and low-calorie (cucumbers)","Healthy fats from guacamole","Provides essential vitamins and minerals"]}}'
//                ],
//                [
//                    "role" => "user",
//                    "content" => 'create a day meal plan for thursday, return only json'
//                ]
            ],
            'temperature' => 1.0,
            'max_tokens' => 3000,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
            'response_format' => [
                "type" => "json_object"
            ]
        ]);

        $completion = json_decode($complete, true);

        $content = isset($completion['choices'][0]['message']['content']) ? $completion['choices'][0]['message']['content'] : null;

        if (isset($completion['choices'][0]['finish_reason']) && $completion['choices'][0]['finish_reason'] !== 'stop') {
            error_log('MALFORMED MEAL PLAN RETURNED:');
        }

        return [
            'day' => $day_name,
            'plan' => $content,
            'message' => $completion['choices'][0]['message'] ?? ''
        ];
    }

    /**
     * @return array[]
     */
    public static function example_day_meals()
    {
        return array (
            'breakfast' =>
                array (
                    'name' => 'Avocado Toast with Egg',
                    'ingredients' =>
                        array (
                            'Whole-grain bread slices' =>
                                array (
                                    'unit' => 'units',
                                    'value' => 2,
                                ),
                            'Avocado' =>
                                array (
                                    'unit' => 'units',
                                    'value' => 1,
                                ),
                            'Eggs' =>
                                array (
                                    'unit' => 'units',
                                    'value' => 2,
                                ),
                            'Salt and pepper' =>
                                array (
                                    'unit' => '',
                                    'value' => 'To taste',
                                ),
                        ),
                    'recipe' =>
                        array (
                            'steps' =>
                                array (
                                    0 =>
                                        array (
                                            'step' => 'Toast %s slices of whole-grain bread.',
                                            'units' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'unit' => 'units',
                                                            'value' => 2,
                                                        ),
                                                ),
                                        ),
                                    1 =>
                                        array (
                                            'step' => 'Mash %s of ripe avocado and spread it evenly on the toast.',
                                            'units' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'unit' => 'units',
                                                            'value' => 1,
                                                        ),
                                                ),
                                        ),
                                    2 =>
                                        array (
                                            'step' => 'Fry or poach %s eggs and place them on top of the avocado.',
                                            'units' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'unit' => 'units',
                                                            'value' => 2,
                                                        ),
                                                ),
                                        ),
                                    3 =>
                                        array (
                                            'step' => 'Season with salt and pepper to taste.',
                                        ),
                                ),
                        ),
                    'duration' =>
                        array (
                            'unit' => 'minutes',
                            'value' => 10,
                        ),
                    'macronutrients' =>
                        array (
                            'Carbohydrates' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 30,
                                ),
                            'Protein' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 15,
                                ),
                            'Fat' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 20,
                                ),
                        ),
                    'calories' => 350,
                    'dietary_info' =>
                        array (
                            'Vegan' => false,
                            'Vegetarian' => true,
                            'Gluten Intolerant' => true,
                            'Glucose Intolerant' => false,
                        ),
                    'benefits' =>
                        array (
                            0 => 'Healthy fats from avocado',
                            1 => 'Protein-packed eggs',
                            2 => 'Whole grains for sustained energy',
                        ),
                ),
            'morning_snack' =>
                array (
                    'name' => 'Greek Yogurt with Honey and Walnuts',
                    'ingredients' =>
                        array (
                            'Greek yogurt' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 150,
                                ),
                            'Honey' =>
                                array (
                                    'unit' => 'tablespoon',
                                    'value' => 1,
                                ),
                            'Walnuts' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 30,
                                ),
                        ),
                    'recipe' =>
                        array (
                            'steps' =>
                                array (
                                    0 =>
                                        array (
                                            'step' => 'Scoop %s of Greek yogurt into a bowl.',
                                            'units' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'unit' => 'grams',
                                                            'value' => 150,
                                                        ),
                                                ),
                                        ),
                                    1 =>
                                        array (
                                            'step' => 'Drizzle %s of honey over the yogurt.',
                                            'units' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'unit' => 'tablespoon',
                                                            'value' => 1,
                                                        ),
                                                ),
                                        ),
                                    2 =>
                                        array (
                                            'step' => 'Sprinkle %s of walnuts on top.',
                                            'units' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'unit' => 'grams',
                                                            'value' => 30,
                                                        ),
                                                ),
                                        ),
                                    3 =>
                                        array (
                                            'step' => 'Mix well and enjoy.',
                                        ),
                                ),
                        ),
                    'duration' =>
                        array (
                            'unit' => 'minutes',
                            'value' => 5,
                        ),
                    'macronutrients' =>
                        array (
                            'Carbohydrates' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 20,
                                ),
                            'Protein' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 15,
                                ),
                            'Fat' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 10,
                                ),
                        ),
                    'calories' => 250,
                    'dietary_info' =>
                        array (
                            'Vegan' => false,
                            'Vegetarian' => true,
                            'Gluten Intolerant' => true,
                            'Glucose Intolerant' => false,
                        ),
                    'benefits' =>
                        array (
                            0 => 'Probiotics from Greek yogurt',
                            1 => 'Antioxidant-rich honey',
                            2 => 'Omega-3 fatty acids from walnuts',
                        ),
                ),
            'lunch' =>
                array (
                    'name' => 'Caprese Salad',
                    'ingredients' =>
                        array (
                            'Tomatoes' =>
                                array (
                                    'unit' => 'units',
                                    'value' => 2,
                                ),
                            'Fresh mozzarella' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 125,
                                ),
                            'Fresh basil leaves' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 20,
                                ),
                            'Balsamic glaze' =>
                                array (
                                    'unit' => 'tablespoon',
                                    'value' => 1,
                                ),
                            'Olive oil' =>
                                array (
                                    'unit' => 'tablespoon',
                                    'value' => 1,
                                ),
                            'Salt and pepper' =>
                                array (
                                    'unit' => '',
                                    'value' => 'To taste',
                                ),
                        ),
                    'recipe' =>
                        array (
                            'steps' =>
                                array (
                                    0 =>
                                        array (
                                            'step' => 'Slice %s of tomatoes and %s of fresh mozzarella.',
                                            'units' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'unit' => 'units',
                                                            'value' => 2,
                                                        ),
                                                    1 =>
                                                        array (
                                                            'unit' => 'grams',
                                                            'value' => 125,
                                                        ),
                                                ),
                                        ),
                                    1 =>
                                        array (
                                            'step' => 'Arrange tomato and mozzarella slices on a plate, alternating with fresh basil leaves.',
                                        ),
                                    2 =>
                                        array (
                                            'step' => 'Drizzle %s of balsamic glaze and %s of olive oil over the salad.',
                                            'units' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'unit' => 'tablespoon',
                                                            'value' => 1,
                                                        ),
                                                    1 =>
                                                        array (
                                                            'unit' => 'tablespoon',
                                                            'value' => 1,
                                                        ),
                                                ),
                                        ),
                                    3 =>
                                        array (
                                            'step' => 'Season with salt and pepper to taste.',
                                        ),
                                ),
                        ),
                    'duration' =>
                        array (
                            'unit' => 'minutes',
                            'value' => 10,
                        ),
                    'macronutrients' =>
                        array (
                            'Carbohydrates' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 10,
                                ),
                            'Protein' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 15,
                                ),
                            'Fat' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 15,
                                ),
                        ),
                    'calories' => 250,
                    'dietary_info' =>
                        array (
                            'Vegan' => false,
                            'Vegetarian' => true,
                            'Gluten Intolerant' => true,
                            'Glucose Intolerant' => false,
                        ),
                    'benefits' =>
                        array (
                            0 => 'Rich in vitamins (tomatoes and basil)',
                            1 => 'Protein from fresh mozzarella',
                            2 => 'Healthy fats from olive oil',
                        ),
                ),
            'afternoon_snack' =>
                array (
                    'name' => 'Banana and Almond Butter',
                    'ingredients' =>
                        array (
                            'Banana' =>
                                array (
                                    'unit' => 'units',
                                    'value' => 1,
                                ),
                            'Almond butter' =>
                                array (
                                    'unit' => 'tablespoon',
                                    'value' => 2,
                                ),
                        ),
                    'recipe' =>
                        array (
                            'steps' =>
                                array (
                                    0 =>
                                        array (
                                            'step' => 'Slice %s of banana.',
                                            'units' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'unit' => 'units',
                                                            'value' => 1,
                                                        ),
                                                ),
                                        ),
                                    1 =>
                                        array (
                                            'step' => 'Spread %s of almond butter on the banana slices.',
                                            'units' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'unit' => 'tablespoon',
                                                            'value' => 2,
                                                        ),
                                                ),
                                        ),
                                    2 =>
                                        array (
                                            'step' => 'Enjoy as a quick and satisfying snack.',
                                        ),
                                ),
                        ),
                    'duration' =>
                        array (
                            'unit' => 'minutes',
                            'value' => 5,
                        ),
                    'macronutrients' =>
                        array (
                            'Carbohydrates' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 30,
                                ),
                            'Protein' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 5,
                                ),
                            'Fat' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 10,
                                ),
                        ),
                    'calories' => 220,
                    'dietary_info' =>
                        array (
                            'Vegan' => true,
                            'Vegetarian' => true,
                            'Gluten Intolerant' => true,
                            'Glucose Intolerant' => true,
                        ),
                    'benefits' =>
                        array (
                            0 => 'Natural sugars for quick energy',
                            1 => 'Protein and healthy fats from almond butter',
                        ),
                ),
            'dinner' =>
                array (
                    'name' => 'Pasta Primavera',
                    'ingredients' =>
                        array (
                            'Whole-grain pasta' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 150,
                                ),
                            'Broccoli florets' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 100,
                                ),
                            'Cherry tomatoes' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 75,
                                ),
                            'Carrots (sliced)' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 50,
                                ),
                            'Bell peppers (sliced)' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 50,
                                ),
                            'Olive oil' =>
                                array (
                                    'unit' => 'tablespoon',
                                    'value' => 1,
                                ),
                            'Garlic (minced)' =>
                                array (
                                    'unit' => 'teaspoon',
                                    'value' => 1,
                                ),
                            'Italian seasoning' =>
                                array (
                                    'unit' => 'teaspoon',
                                    'value' => 1,
                                ),
                            'Parmesan cheese (grated)' =>
                                array (
                                    'unit' => 'tablespoon',
                                    'value' => 1,
                                ),
                            'Salt and pepper' =>
                                array (
                                    'unit' => '',
                                    'value' => 'To taste',
                                ),
                        ),
                    'recipe' =>
                        array (
                            'steps' =>
                                array (
                                    0 =>
                                        array (
                                            'step' => 'Cook %s of whole-grain pasta according to package instructions.',
                                            'units' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'unit' => 'grams',
                                                            'value' => 150,
                                                        ),
                                                ),
                                        ),
                                    1 =>
                                        array (
                                            'step' => 'In a pan, sauté %s of minced garlic in %s of olive oil until fragrant.',
                                            'units' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'unit' => 'teaspoon',
                                                            'value' => 1,
                                                        ),
                                                    1 =>
                                                        array (
                                                            'unit' => 'tablespoon',
                                                            'value' => 1,
                                                        ),
                                                ),
                                        ),
                                    2 =>
                                        array (
                                            'step' => 'Add %s of broccoli, %s of cherry tomatoes, %s of sliced carrots, and %s of sliced bell peppers to the pan. Cook until vegetables are tender.',
                                            'units' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'unit' => 'grams',
                                                            'value' => 100,
                                                        ),
                                                    1 =>
                                                        array (
                                                            'unit' => 'grams',
                                                            'value' => 75,
                                                        ),
                                                    2 =>
                                                        array (
                                                            'unit' => 'grams',
                                                            'value' => 50,
                                                        ),
                                                    3 =>
                                                        array (
                                                            'unit' => 'grams',
                                                            'value' => 50,
                                                        ),
                                                ),
                                        ),
                                    3 =>
                                        array (
                                            'step' => 'Toss in cooked pasta and %s of Italian seasoning. Season with salt and pepper to taste.',
                                            'units' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'unit' => 'teaspoon',
                                                            'value' => 1,
                                                        ),
                                                ),
                                        ),
                                    4 =>
                                        array (
                                            'step' => 'Serve topped with %s of grated Parmesan cheese.',
                                            'units' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'unit' => 'tablespoon',
                                                            'value' => 1,
                                                        ),
                                                ),
                                        ),
                                ),
                        ),
                    'duration' =>
                        array (
                            'unit' => 'minutes',
                            'value' => 20,
                        ),
                    'macronutrients' =>
                        array (
                            'Carbohydrates' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 40,
                                ),
                            'Protein' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 15,
                                ),
                            'Fat' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 10,
                                ),
                        ),
                    'calories' => 350,
                    'dietary_info' =>
                        array (
                            'Vegan' => false,
                            'Vegetarian' => true,
                            'Gluten Intolerant' => false,
                            'Glucose Intolerant' => false,
                        ),
                    'benefits' =>
                        array (
                            0 => 'Fiber-rich whole-grain pasta',
                            1 => 'Abundance of colorful vegetables',
                            2 => 'Healthy fats from olive oil',
                        ),
                ),
            'evening_snack' =>
                array (
                    'name' => 'Apple Slices with Peanut Butter',
                    'ingredients' =>
                        array (
                            'Apple' =>
                                array (
                                    'unit' => 'units',
                                    'value' => 1,
                                ),
                            'Peanut butter' =>
                                array (
                                    'unit' => 'tablespoon',
                                    'value' => 2,
                                ),
                        ),
                    'recipe' =>
                        array (
                            'steps' =>
                                array (
                                    0 =>
                                        array (
                                            'step' => 'Slice %s of apple.',
                                            'units' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'unit' => 'units',
                                                            'value' => 1,
                                                        ),
                                                ),
                                        ),
                                    1 =>
                                        array (
                                            'step' => 'Spread %s of peanut butter on the apple slices.',
                                            'units' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'unit' => 'tablespoon',
                                                            'value' => 2,
                                                        ),
                                                ),
                                        ),
                                    2 =>
                                        array (
                                            'step' => 'Enjoy as a delightful and nutritious snack.',
                                        ),
                                ),
                        ),
                    'duration' =>
                        array (
                            'unit' => 'minutes',
                            'value' => 5,
                        ),
                    'macronutrients' =>
                        array (
                            'Carbohydrates' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 30,
                                ),
                            'Protein' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 7,
                                ),
                            'Fat' =>
                                array (
                                    'unit' => 'grams',
                                    'value' => 15,
                                ),
                        ),
                    'calories' => 250,
                    'dietary_info' =>
                        array (
                            'Vegan' => true,
                            'Vegetarian' => true,
                            'Gluten Intolerant' => true,
                            'Glucose Intolerant' => true,
                        ),
                    'benefits' =>
                        array (
                            0 => 'Natural sugars and fiber from apple',
                            1 => 'Protein and healthy fats from peanut butter',
                        ),
                ),
        );
    }
}
