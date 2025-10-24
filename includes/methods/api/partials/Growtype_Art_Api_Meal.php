<?php

class Growtype_Art_Api_Meal
{
    public function __construct()
    {
        $this->load_methods();

        add_action('rest_api_init', array (
            $this,
            'register_routes'
        ));
    }

    function load_methods()
    {

    }

    function register_routes()
    {
        $permission = current_user_can('manage_options');

        /**
         * Get mealplan
         */
        register_rest_route('growtype-art/v1', 'get/mealplan', array (
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array (
                $this,
                'get_mealplan_callback'
            ),
            'permission_callback' => function ($user) use ($permission) {
                return true;
            }
        ));

        /**
         * Meal plan
         */
//        register_rest_route('growtype-art/v1', 'generate/mealplan/(?P<days>\d+)', array (
//            'methods' => 'POST',
//            'callback' => array (
//                $this,
//                'generate_mealplan_callback'
//            ),
//            'permission_callback' => function ($user) use ($permission) {
//                return true;
//            }
//        ));

        /**
         * Meal plan
         */
        register_rest_route('growtype-art/v1', 'generate/mealplan/day', array (
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array (
                $this,
                'generate_mealplan_day_callback'
            ),
            'permission_callback' => function ($user) use ($permission) {
                return true;
            }
        ));

        /**
         * Generate meal
         */
        register_rest_route('growtype-art/v1', 'generate/meal/(?P<meal_type>\w+)', array (
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array (
                $this,
                'generate_meal_callback'
            ),
            'permission_callback' => function ($user) use ($permission) {
                return true;
            }
        ));

        /**
         * Get meal
         */
        register_rest_route('growtype-art/v1', 'get/meal/(?P<meal_slug>[a-zA-Z0-9-]+)', array (
            'methods' => WP_REST_Server::READABLE,
            'callback' => array (
                $this,
                'get_meal_callback'
            ),
            'permission_callback' => function ($user) use ($permission) {
                return true;
            }
        ));
    }

    function get_mealplan_callback($data)
    {
        $params = $data->get_params();
        $user_details = isset($params['user_details']) ? json_decode($params['user_details'], true) : [];

        if (empty($user_details)) {
            return wp_send_json([
                'success' => false,
            ], 200);
        }

        $user_details = self::sanitize_user_details($user_details);

        if (empty($user_details)) {
            return wp_send_json([
                'success' => false,
                'message' => 'Before obtaining your personalized meal plan, kindly complete a personalized meal plan quiz.',
            ], 200);
        }

        $openai_base_meal = new Openai_Base_Meal();

        $meal_plan = $openai_base_meal->get_plan($user_details);

        if (empty($meal_plan)) {
            $openai_base_meal->generate_plan($user_details, 1);

            return wp_send_json([
                'success' => false,
                'message' => 'Your personalized meal plan is currently being generated. Typically, it takes around 10 minutes to prepare a plan. Thank you for your patience.',
            ], 200);
        }

        return wp_send_json([
            'success' => true,
            'plan' => $meal_plan
        ], 200);
    }

    function generate_mealplan_callback($data)
    {
        $params = $data->get_params();
        $days = isset($params['days']) ? $params['days'] : 0;
        $user_details = isset($params['user_details']) ? json_decode($params['user_details'], true) : [];

        if (empty($days) || empty($user_details)) {
            return wp_send_json([
                'success' => false,
            ], 200);
        }

        $user_details = self::sanitize_user_details($user_details);

        if (empty($user_details)) {
            return wp_send_json([
                'success' => false,
                'message' => 'Before obtaining your personalized meal plan, kindly complete a personalized meal plan quiz.',
            ], 200);
        }

        $openai_base_meal = new Openai_Base_Meal();

        $meal_plan = $openai_base_meal->generate_plan($user_details, $days);

//        $meal_plan[0]['meals'] = Generate_Meal_Plan_Job::example_day_meals();

        if (empty($meal_plan)) {
            return wp_send_json([
                'success' => false,
                'message' => 'Your personalized meal plan is currently being generated. Typically, it takes around 10 minutes to prepare a plan. Thank you for your patience.',
            ], 200);
        }

        return wp_send_json([
            'success' => true,
            'plan' => $meal_plan
        ], 200);
    }

    function generate_mealplan_day_callback($data)
    {
        $params = $data->get_params();

        $day_name = isset($params['day_name']) ? $params['day_name'] : '';
        $user_details = isset($params['user_details']) ? json_decode($params['user_details'], true) : [];

        if (empty($day_name) || empty($user_details)) {
            return wp_send_json([
                'message' => 'Missing information.',
                'success' => false,
            ], 200);
        }

        $user_details = self::sanitize_user_details($user_details);

        if (empty($user_details)) {
            return wp_send_json([
                'success' => false,
                'message' => 'Before obtaining your personalized meal plan, kindly complete a personalized meal plan quiz.',
            ], 200);
        }

        $openai_base_meal = new Openai_Base_Meal();

        $meal_plan_day = $openai_base_meal->generate_day($user_details, $day_name);

        if (empty($meal_plan_day)) {
            return wp_send_json([
                'success' => false,
                'message' => sprintf('%s Meal plan is currently being generated. Typically, it takes around 5 minutes to prepare a plan. Thank you for your patience.', $day_name)
            ], 200);
        }

        return wp_send_json([
            'success' => true,
            'meal_plan_day' => $meal_plan_day
        ], 200);
    }

    public static function sanitize_user_details($user_details)
    {
        $gender = isset($user_details['gender']) ? $user_details['gender'] : '';
        $primary_goal = isset($user_details['primary_goal']) ? $user_details['primary_goal'] : '';
        $dietary_preferences = isset($user_details['dietary_preferences']) ? $user_details['dietary_preferences'] : '';
        $food_allergies = isset($user_details['food_allergies']) ? $user_details['food_allergies'] : '';
        $medical_conditions = isset($user_details['medical_conditions']) ? $user_details['medical_conditions'] : '';
        $exercise_frequency = isset($user_details['exercise_frequency']) ? $user_details['exercise_frequency'] : '';
        $weekly_workouts = isset($user_details['weekly_workouts']) ? $user_details['weekly_workouts'] : '';
        $age = isset($user_details['age'][0]['value']) ? $user_details['age'][0]['value'] : '';
        $height = isset($user_details['height'][0]['value']) ? $user_details['height'][0]['value'] : '';
        $current_weight = isset($user_details['current_weight'][0]['value']) ? $user_details['current_weight'][0]['value'] : '';
        $target_weight = isset($user_details['target_weight'][0]['value']) ? $user_details['target_weight'][0]['value'] : '';
        $workout_duration = isset($user_details['workout_duration']) ? $user_details['workout_duration'] : '';
        $coach_gender_preference = isset($user_details['coach_gender_preference']) ? $user_details['coach_gender_preference'] : '';
        $sleep_trouble_frequency = isset($user_details['sleep_trouble_frequency']) ? $user_details['sleep_trouble_frequency'] : '';
        $unit_system = isset($user_details['unit_system']) ? $user_details['unit_system'] : '';
        $meal_count_per_day = isset($user_details['meal_count_per_day']) ? $user_details['meal_count_per_day'] : 3;

        if (
            empty($gender)
            || empty($primary_goal)
            || empty($dietary_preferences)
            || empty($food_allergies)
            || empty($medical_conditions)
            || empty($exercise_frequency)
            || empty($weekly_workouts)
            || empty($age)
            || empty($height)
            || empty($current_weight)
            || empty($target_weight)
        ) {
            return [];
        }

        return [
            'gender' => $gender,
            'primary_goal' => $primary_goal,
            'dietary_preferences' => $dietary_preferences,
            'food_allergies' => $food_allergies,
            'medical_conditions' => $medical_conditions,
            'exercise_frequency' => $exercise_frequency,
            'weekly_workouts' => $weekly_workouts,
            'age' => $age,
            'height' => $height,
            'current_weight' => $current_weight,
            'target_weight' => $target_weight,
            'meal_count_per_day' => $meal_count_per_day,
        ];
    }

    function generate_meal_callback($data)
    {
        $params = $data->get_params();
        $meal_type = isset($params['meal_type']) ? $params['meal_type'] : '';
        $user_details = isset($params['user_details']) ? json_decode($params['user_details'], true) : [];

        if (empty($meal_type) || empty($user_details)) {
            return wp_send_json([
                'success' => false,
            ], 200);
        }

        $user_details = self::sanitize_user_details($user_details);

        if (empty($user_details)) {
            return wp_send_json([
                'success' => false,
                'message' => 'Before obtaining your personalized meal plan, kindly complete a personalized meal plan quiz.',
            ], 200);
        }

        $openai_base_meal = new Openai_Base_Meal();

        $meal = $openai_base_meal->generate_meal($user_details, $meal_type);

        if (empty($meal)) {
            return wp_send_json([
                'success' => false,
                'message' => 'Meal is currently being generated. Typically, it takes around 2 minutes to prepare a meal. Thank you for your patience.',
            ], 200);
        }

        return wp_send_json([
            'success' => true,
            'meal' => $meal
        ], 200);
    }

    function get_meal_callback($data)
    {
        $params = $data->get_params();
        $slug = isset($params['meal_slug']) ? $params['meal_slug'] : '';

        $args = array (
            'name' => $slug,
            'post_type' => 'meal',
            'post_status' => 'any',
            'numberposts' => 1
        );

        $meal_post = get_posts($args);

        $meal = '';
        if (!empty($meal_post)) {
            $meal = json_decode($meal_post[0]->post_content, true);
        }

        return wp_send_json([
            'success' => true,
            'meal' => $meal
        ], 200);
    }
}
