<?php

use Orhanerday\OpenAi\OpenAi;

class Generate_Meal_Job
{
    public function run($job)
    {
        $job_payload = json_decode($job['payload'], true);

        $user_details = $job_payload['user_details'] ?? '';
        $meal_type = $job_payload['meal_type'] ?? '';

        if (empty($user_details) || empty($meal_type)) {
            return '';
        }

        $meal_details = $this->create_meal($user_details, $meal_type);
        $meal = $meal_details['meal'];

        if (!empty($meal)) {
            $meal = json_decode($meal, true);

            if (empty($meal)) {
                $meal = Openai_Base_Meal::fix_malformed_json($meal);

                if (!empty($meal)) {
                    $meal = json_decode($meal);
                }
            }

            if (!empty($meal)) {
                global $wpdb;

                $post_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_content = '" . json_encode($meal) . "' AND post_type='meal'");

                if (!$post_id) {
                    $term = get_term_by('slug', $meal_type, 'meal_tax');

                    $new_post = array (
                        'post_type' => 'meal',
                        'post_title' => $meal['name'],
                        'post_content' => json_encode($meal),
                        'post_status' => 'publish',
                        'post_excerpt' => json_encode($user_details),
                    );

                    $post_id = wp_insert_post($new_post);

                    if (!$post_id) {
                        error_log('Error inserting post' . print_r($meal, true));
                    } else {
                        wp_set_post_terms($post_id, [$term->term_id], 'meal_tax');
                    }
                }

                error_log('SINGLE MEAL IMPORTING SUCCESSFUL!!!!');
            } else {
                error_log('SINGLE MEAL MALFORMED JSON!!!! ' . print_r($meal, true));
            }
        } else {
            error_log('EMPTY SINGLE MEAL!' . print_r($meal, true));
        }
    }

    function create_meal($user_details, $meal_type)
    {
        extract($user_details);

        $content = sprintf('Create a %s meal for a %s who has a primary goal to %s, who prefers %s diet, who has %s food allergies, who has %s medican conditions, who exercise %s, who would like to exercise %s per week, whose age is %s, whose height is %s cm, whose weight is %s kg and would like to reach %s kg weight.',
            $meal_type,
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

        error_log('SINGLE MEAL GENERATING STARTED WITH CONTENT: ' . print_r($content, true));

        $open_ai = new OpenAi(Openai_Base::api_key());

        $example_output = self::example_meal();

        $complete = $open_ai->chat([
            'model' => 'gpt-3.5-turbo-1106',
            'messages' => [
                [
                    "role" => "system",
                    "content" => 'You are a professional dietitian specializing in crafting straightforward and easily-followed personalized daily meal plans. Each plan consists of breakfast, morning snack, lunch, afternoon snack, dinner, and evening snack. For each meal, you provide the name, ingredients, recipe with units, duration, macronutrients, calories, dietary information, and benefits. All recipes are designed using products readily available in local supermarkets.'
                ],
                [
                    "role" => "user",
                    "content" => 'create a lunch meal, return only json'
                ],
                [
                    "role" => "system",
                    "content" => json_encode($example_output)
                ],
                [
                    "role" => "user",
                    "content" => $content
                ]
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
            error_log('MALFORMED SINGLE MEAL RETURNED:');
        }

        error_log('SINGLE MEAL COMPLETION: ' . print_r($completion, true));

        return [
            'meal' => $content,
            'message' => $completion['choices'][0]['message'] ?? ''
        ];
    }

    /**
     * @return array[]
     */
    public static function example_meal()
    {
        return array (
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
        );
    }
}
