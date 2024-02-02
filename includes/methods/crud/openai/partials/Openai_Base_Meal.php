<?php

use Ahc\Json\Fixer;

class Openai_Base_Meal extends Openai_Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function generate_day($user_details, $day_name = null)
    {
        if (empty($user_details)) {
            return '';
        }

//        $job = new Generate_Meal_Plan_Job();
//
//        $job->run([
//            'payload' => json_encode(
//                [
//                    'user_details' => $user_details,
//                    'api_key' => $this->open_ai_key
//                ])
//        ]);

        growtype_cron_init_job('generate-meal-plan', json_encode(
            [
                'day_name' => $day_name,
                'user_details' => $user_details,
            ]), 5);
    }

    public function generate_plan($user_details, $days)
    {
        global $wpdb;

        $days = $days > 28 ? 28 : $days;

        $posts = $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE post_excerpt = '" . json_encode($user_details) . "' AND post_type='meal_plan' AND post_status in ('draft','publish')");

        $meal_plan_ids = [];
        $generating_jobs = 0;
        for ($i = 1; $i <= $days; $i++) {

            if ($generating_jobs > 3) {
                break;
            }

            $post_index = $i - 1;

            if (isset($posts[$post_index]) && !empty($posts[$post_index])) {
                $meal_plan_ids[$post_index] = $posts[$post_index]->ID;
            } else {
                $posts = $wpdb->get_results("SELECT * FROM wp_growtype_cron_jobs WHERE payload like '%" . json_encode($user_details) . "%'");

                $left_days = $days - $i;

                if (empty($posts) || count($posts) < $left_days) {
                    $this->generate_day($user_details);
                    $generating_jobs++;
                }
            }
        }

        if (count($meal_plan_ids) !== (int)$days) {
            return [];
        }

        $meal_plan = [];
        foreach ($meal_plan_ids as $day_nr => $day_meals_id) {
            $post = get_post($day_meals_id);

            $meals = json_decode($post->post_content, true);

            $day_meal_plan = [];
            foreach ($meals as $key => $meal) {
                $post = get_post($meal);

                if (!in_array($post->post_status, ['draft', 'publish'])) {
                    continue;
                }

                $day_meal = json_decode($post->post_content, true);
                $meal_id = [
                    'id' => $meal,
                    'slug' => $post->post_name,
                ];

                $day_meal = array_merge($meal_id, $day_meal);

                $day_meal_plan[$key] = $day_meal;
            }

            $meal_plan[$day_nr] = [
                'id' => $day_meals_id,
                'meals' => $day_meal_plan
            ];
        }

        return $meal_plan;
    }

    public function get_plan($user_details)
    {
        global $wpdb;

        $posts = $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE post_excerpt = '" . json_encode($user_details) . "' AND post_type='meal_plan' AND post_status in ('draft','publish')");

        if (empty($posts)) {
            return [];
        }

        $meal_plan = [];
        foreach ($posts as $index => $post) {
            $meals = json_decode($post->post_content, true);

            $day_meal_plan = [];
            foreach ($meals as $key => $meal) {
                $post = get_post($meal);

                if (!in_array($post->post_status, ['draft', 'publish'])) {
                    continue;
                }

                $day_meal = json_decode($post->post_content, true);

                $meal_id = [
                    'id' => $meal,
                    'slug' => $post->post_name,
                ];

                $day_meal = array_merge($meal_id, $day_meal);

                $day_meal_plan[$key] = $day_meal;
            }

            $meal_plan[$index] = [
                'id' => $post->ID,
                'meals' => $day_meal_plan
            ];
        }

        return $meal_plan;
    }

    public static function generate_meal($user_details, $meal_type)
    {
        global $wpdb;

        $payload = json_encode(
            [
                'meal_type' => $meal_type,
                'user_details' => $user_details,
            ]
        );

        $meal = $wpdb->get_results("SELECT * FROM wp_growtype_cron_jobs WHERE payload like '%" . $payload . "%'");

        if (!$meal) {
            growtype_cron_init_job('generate-meal', $payload, 5);
        }
    }

    public static function fix_malformed_json($malformedJson)
    {
        error_log('!!!FIXING MALFORMED JSON!!!');

        return (new Fixer)->fix($malformedJson);
    }
}

