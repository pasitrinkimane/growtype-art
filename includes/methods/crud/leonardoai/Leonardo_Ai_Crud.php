<?php

require GROWTYPE_AI_PATH . '/vendor/autoload.php';

use React\EventLoop\Loop;

class Leonardo_Ai_Crud
{
    const PROVIDER = 'leonardoai';

    public static function user_credentials()
    {
        return [
            '1' => [
                'cookie' => get_option('growtype_ai_leonardo_access_key'),
                'user_id' => get_option('growtype_ai_leonardo_user_id'),
                'access_token' => get_option('growtype_ai_leonardo_access_token')
            ],
            '2' => [
                'cookie' => get_option('growtype_ai_leonardo_access_key_2'),
                'user_id' => get_option('growtype_ai_leonardo_user_id_2'),
                'access_token' => get_option('growtype_ai_leonardo_access_token_2')
            ],
            '3' => [
                'cookie' => get_option('growtype_ai_leonardo_access_key_3'),
                'user_id' => get_option('growtype_ai_leonardo_user_id_3'),
                'access_token' => get_option('growtype_ai_leonardo_access_token_3')
            ]
        ];
    }

    function get_user_credentials($user_nr)
    {
        if (empty($user_nr)) {
            $user_nr = 1;
        }

        return self::user_credentials()[$user_nr];
    }

    public function generate_model($model_id = null)
    {
        $token = $this->get_access_token();

        $generation_details = $this->get_generation_details($token, $model_id);

        growtype_ai_init_job('retrieve-model', json_encode([
            'user_nr' => $generation_details['user_nr'],
            'amount' => 1,
            'model_id' => $model_id,
            'generation_id' => $generation_details['generation_id'],
        ]), 30);
    }

    public function get_generation_details($token, $model_id)
    {
        $user_nr = 1;
        $cookie = $this->get_user_credentials($user_nr)['cookie'];
        $token = $this->retrieve_access_token($cookie);
        $generation_id = $this->init_image_generating($token, $model_id);

        if (empty($generation_id)) {
            $user_nr = 2;
            $cookie = $this->get_user_credentials($user_nr)['cookie'];
            $token = $this->retrieve_access_token($cookie);
            $generation_id = $this->init_image_generating($token, $model_id);

            if (empty($generation_id)) {
                $user_nr = 3;
                $cookie = $this->get_user_credentials($user_nr)['cookie'];
                $token = $this->retrieve_access_token($cookie);
                $generation_id = $this->init_image_generating($token, $model_id);

                if (empty($generation_id)) {
                    throw new Exception('No generationId');
                }
            }
        }

        return [
            'generation_id' => $generation_id,
            'user_nr' => $user_nr
        ];
    }

    public function retrieve_models($amount, $model_id = null, $user_nr = null)
    {
        $token = $this->get_access_token($user_nr);

        $generations = $this->get_generations($token, $amount, $user_nr);

        if (empty($generations)) {
            return null;
        }

        $this->save_generations($generations, $model_id);

        $this->delete_external_generations($token, $generations);

        return $generations;
    }

    public function retrieve_single_generation($model_id, $user_nr, $generation_id)
    {
        $token = $this->get_access_token($user_nr);

        $generations = $this->get_generations($token, 30, $user_nr);

        if (empty($generations)) {
            throw new Exception('Empty generations. Token: ' . $token);
        }

        $single_generation = '';
        foreach ($generations as $generation) {
            if ($generation['id'] === $generation_id) {
                $single_generation = $generation;
                break;
            }
        }

        if (empty($single_generation)) {
            throw new Exception('No generation');
        }

        $generations = [$single_generation];

        /**
         * Save generation
         */
        $this->save_generations($generations, $model_id);

        /**
         * Delete generation from Leonardo.ai
         */
        $this->delete_external_generations($token, $generations);

        return $generations;
    }

//    ------

    public function get_access_token($user_nr = null)
    {
        $cookie = $this->get_user_credentials($user_nr)['cookie'];
        $access_token = $this->get_user_credentials($user_nr)['access_token'];

        $token = !empty($access_token) ? $access_token : $this->retrieve_access_token($cookie);

        if (empty($token)) {
            throw new Exception('No token user_nr: ' . $user_nr);
        }

        return $token;
    }

    function get_login_details($cookie)
    {
        $url = 'https://app.leonardo.ai/api/auth/me';

        $response = wp_remote_post($url, array (
            'headers' => array (
                'cookie' => $cookie,
            ),
            'method' => 'GET',
            'data_format' => 'body',
        ));

        $body = wp_remote_retrieve_body($response);

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return $responceData;
    }

    function retrieve_access_token($cookie)
    {
        $url = 'https://app.leonardo.ai/api/auth/session';

        $response = wp_remote_post($url, array (
            'headers' => array (
                'cookie' => 'intercom-device-id-xc8vmlt4=6e99c08d-d25a-4c90-a4bb-af0e298fe9ba; _rdt_uuid=1677587760192.d3ddfc74-ce39-456b-9114-8364c0a0ecf4; _fbp=fb.1.1678823687990.1474663959; __stripe_mid=748cbef3-dc07-4a02-8abf-b975acb295f8083487; _gid=GA1.2.1741148437.1680174508; _gat_gtag_UA_252259754_1=1; _ga_9SZY51046C=GS1.1.1680174507.53.0.1680174507.60.0.0; _ga=GA1.1.1788738648.1677502597; __Host-next-auth.csrf-token=41b8aa3243e7ab8a7e0ca95a767a4c2a4f1ff8d72e6fbb3e2707ecd62aaa776e%7C8cd8f62b6da56d1788f513cfc3782ce270289d582540e1c9ab5378e496fdadb5; __stripe_sid=150e0545-ec15-47b4-84f4-9eba5466da53967d01; __Secure-next-auth.callback-url=https%3A%2F%2Fapp.leonardo.ai%2F; accessToken=' . $cookie . '; idToken=eyJraWQiOiJtM1IxVnh4VWlEa1Q3Z1lrc3dYWlBFb1JEcnRWU0E0M3E0bUtzc29ZWWpZPSIsImFsZyI6IlJTMjU2In0.eyJhdF9oYXNoIjoiTmotMThQMmhEMzh2RzJnNDBycUI5USIsInN1YiI6ImE2MzZlM2Q3LTI0MWMtNDg2Zi04MzgxLTAwMDhiM2ZhZTYzMyIsImNvZ25pdG86Z3JvdXBzIjpbInVzLWVhc3QtMV94a1ZNdUNxZXVfR29vZ2xlIl0sImVtYWlsX3ZlcmlmaWVkIjpmYWxzZSwiaHR0cHM6XC9cL2hhc3VyYS5pb1wvand0XC9jbGFpbXMiOiJ7XCJ4LWhhc3VyYS11c2VyLWlkXCI6XCIzZGIxYjZjYS0wZTU2LTQxMWMtYmMyNC1hZGI2Y2E0ZTY0NDRcIixcIngtaGFzdXJhLWRlZmF1bHQtcm9sZVwiOlwidXNlclwiLFwieC1oYXN1cmEtYWxsb3dlZC1yb2xlc1wiOltcInVzZXJcIl19IiwiaXNzIjoiaHR0cHM6XC9cL2NvZ25pdG8taWRwLnVzLWVhc3QtMS5hbWF6b25hd3MuY29tXC91cy1lYXN0LTFfeGtWTXVDcWV1IiwiY29nbml0bzp1c2VybmFtZSI6Imdvb2dsZV8xMDU4ODM2MTg1ODI1MzQyMjQyMTkiLCJnaXZlbl9uYW1lIjoiTmV3Y29vbCBTdHVkaW8iLCJvcmlnaW5fanRpIjoiZmZmMzkxZTQtN2FlNi00YTUyLWFhYzQtZjc0M2FjYWRiN2E3IiwiYXVkIjoiOXNhMWRsaDZqNHU2ZTRmaXYxYzEyNDRwcSIsImlkZW50aXRpZXMiOlt7InVzZXJJZCI6IjEwNTg4MzYxODU4MjUzNDIyNDIxOSIsInByb3ZpZGVyTmFtZSI6Ikdvb2dsZSIsInByb3ZpZGVyVHlwZSI6Ikdvb2dsZSIsImlzc3VlciI6bnVsbCwicHJpbWFyeSI6InRydWUiLCJkYXRlQ3JlYXRlZCI6IjE2ODAxNTA2MzU3MTAifV0sInRva2VuX3VzZSI6ImlkIiwiYXV0aF90aW1lIjoxNjgwMTc0NTIzLCJuYW1lIjoiTmV3Y29vbCBTdHVkaW8iLCJleHAiOjE2ODAxNzgxMjYsImlhdCI6MTY4MDE3NDUyNiwianRpIjoiNThkMDI2YTUtNDZlNS00NGRhLWIzZGUtMWI0MTQ1YTQ4ZDg3IiwiZW1haWwiOiJuZXdjb29sc3R1ZGlvQGdtYWlsLmNvbSJ9.YVZDY_ZdOuszfrbRaykW3bldelEcAZEsoaEE9HbhkVEab88W8cLdsdUDmJewWd6Yr-qmt6z9Iy7F5_pf-hB9p6oZUl3lptKJDHlgj2df1hUXuTNRzFO6YWd4h9PjyIjh9e_oXKAfLfBC5YKvnPlSRZToqyILNx_ZoL0WdRIlTSlCTcK1HCTWGDG5T5LOY06zLtvz15M8X3C5OCo4BXli3beaAB9996nHZ2d14S3kY6yxswvzKVXZan_XOzpMNDu6JohawhfyNP4yMlZCxYj2Ay1ymqHUjWA5YoiaYLFpkHCqQ3FdyEr_OY3unrTL4AGXWwrygVfuUYdFfZDcsYPTXg; intercom-session-xc8vmlt4=Y3lkQlNCWnhWZWhPSUx5TGRyZzBRQk00SWJyRVBFUmdjcFhwNldvYlAwQVZDQ2VOTitlQ0k4dzlwdWZqb3gwNC0tWGk1aTRsWVE3R005MWNJY1lIeFRhdz09--f6781df6961e375d0aff1cb3bd2f14866aec2b1d; _ga_4J9ZXN1KG8=GS1.1.1680174512.99.1.1680174536.0.0.0; __Secure-next-auth.session-token.0=eyJhbGciOiJkaXIiLCJlbmMiOiJBMjU2R0NNIn0..Ypm6L8qN0rccaoCv.iBYzMkC4wnhr2pukxQbwk_Woi1Bor96DOjWspES5Pq_WiZvpIM9zFeqA8s60fN_W1KKF98u4I_rQh-PnxUxdtJDr1EhvwAsrWh_ruOPX67rV--IEUkLLhL4Qh9SQ-u4m5duBHzsPE2lRW6rjiEUG2L68f5hZH1jrueg591-jOVUOQzL6iSFWtuHzla-6wUFUGp5laL_nhOJX9GewwGXDr8oZNMQuzGlJTvfwOoM3Zgwty73h-AVZbY0GUvHdZuSq-jSo71Mzbxluw4gvWtac0SWgtXt3MF27_zcQd26iQXH-zNm3VPXxc0riFwnu1A3RHIp8PCQ2djPcX8PApLSNOW5yZ6gEOzexOQIF5mqO_pLE8IfIWtKs13Yeh_IWqI_ECc5ymZEZFNkw6FJpnniVYXUMjwyoP-CK8-Wu1S99itFPODfjFbcv33vgKO25le_7T7UR0pwhzFotv7oOfeZdQRr5LCkW3F-RNuMfeN23IuUZ6CafvBlX26Fr_efyYu0SANtl5F-3huzwn_61YjrPswdPD76DrCy0u4DmAhba-W1caSDsQcwWnm8ONldyptofOORdPi3FHFtcl9pcZxokEiKGvPoKrmUzg3amBsykOJZGsepWTBrE2z8PyQ3kvme7eZLtJcdYLaHmt4RlBYuZqJcvEbXGFkb_ZOg28fqOrT2YaA59zzWTE83NJe1-wZvnBbRFR8HJZ5Mgo21aIDVTR2dLZRaKRen-FDacgZuppctrdyUEx58riHAEnsjsPbinEyVm1OD7T6Mudm6pGFK0RJjM3rAmjc1crobG1wD9R5jY8cpJQAQKtGE1ZBxQGQGi4JdbDEiQBIBJgmQAC4CzWmFMrxsm3AW3JwuDQ5BZfNOxuv_NTpiZf24__M0P1szIeEIYYz4psPLjrqBVlT9lI0dXm6auc8ASI0nATh5gLqraWeii7RE0DpXdhn_cej-8cLEQGGseZ7YN2MkudjTKH9HFvkVcnif_oL21yMLDmm-iHk4sH5eubTr50W5xR3tAGLxJwhBN2zQL_vRC0-uoJ4eSxeiKzgcUFcozYYfEbwXgfwHdz4mfcc4jBv3oWtRVQeEY_uXFYyPDDtMomRAIMm9NfKCIaQ-qvX9BM4mDcLAPjfVSV02udg4o7YvTja5GEJwRT6IsRqWFpgWEu2J6ZQrFQ6Whf3b3HvVKWWXCYZy5HN1FVKOgDtK-4SJUQV6DTmSiwiept99ZoTO9bsPwg3NgMaNon5fKbErEXFxPZZvIqKvmqLA8fljUEquZfbmql9jnZXUt6fFJhlPxDi3Z_RmEl0Ep0FGXzmRmEK-Yejuggi8kNrRliz1Nu-CqRO4eIf7w9HVN8Beat4BL4yYW_Zm9RDDcmho69hiEF-YXyNN-Ry_qqwMH3V2VpfZahQZhm5S65CstSz5PSTfk_eshQxfIkrR1TQJerpdc2Fv2ohedusgQG-ETacxZW_nVzbq474_atq5SBSdFOeDnkLxZl_fNkYHsdIPJlFhLXA1e5YcS3_DelA0P7UA8EWvXmLGKTec4X7LGyeHoLp3Ojt-CV1Xr82JXRiznY9JnStXcVAizice0x7awej7sr86pbJ1PGfH4C6yS8Lkxa5vkPaOY67v5S4BZbkKviclg1BG3F82YbQFYJvFq98JqF8cCdmh0EFOe_HuB16aHdGLG79w6DAXf7wMYiUHbSfl0PqrgAgTGzSxVtrhW4htKzVy58iqFuNbah1Q53jQMPOngJcbW_8Pm5h9jziaAD9zMzyU7c32mE1lpB9XBh4-_D1e8yV9loxVXp4yp48OH4Fn-HtLepnOhmsnMaYPsvEwuKVJWULW6hBisSDwT8lKECEuU0dagQx2oJny_K7rtOIuy44L63MEcZfrQ9Z-Am9OlU6bA9x2l1mK5_VkDT2ml8BNYzDvxW4HMcQvCc5d99UnWGsEZdA0omnWVeP_xNSsQQmX5rSPWF-90V7p3iyTuymE0HFaJwCawz2_q89w8Z9U0E_WPAwGPsmrwQkcI3tcikOPxHA3pT1wyiGAmHd-TsEtemiPROTZ7sRHiD8ZycfmL7k9IamXJVtrzwkse0bsvX86Y4Y3QmJzycGZoMjkjGfdqDDY2Dp3K73uWUOb5FBUTpDOWlohlKLpwAU4k2lsder_Zfjh7-POJpHdMcM5bSoN6z4PP6mK0WFFW4aZ3Mb_9Pnlz1YJ2GtEnmBg1cdb4_US5mGZqVRGflH9KiNL_Zebzo_hgDvtoZeMu5-CEMuNOpozKufU5x4feEzXBkqxhMILVEUm-N2_JO4La8XI0lOZpYEtbAC_Ed2XA8qkA89_wkv7RW1Ux0AlzBTi8LuawzSYr--ctJSn6O7o6nW2DYUm3cjhwtodjj25KletVyk6C3jtbTTdEsRnkyvzzoCvOrqgeShxOMEto5wHHWwYjyoUJGExUbgvyJVDlZkt5AUesZ4Me2GxTiYEehsMtrY0HF0i6HMDuLLRvDKXa8si4GrDrpya67wyT3i0YGTGoI2ES1ITDhv9Mrmba-m1kkI6PwKYXcmtlw4rYMNIHS9ExkLKvJJ4JwUMsDC38q2CUgp3lHJ9JKMtcymekQIGrQJk1g5dwjOglP1_644V6tQFN_0L17clzOTLmOE2vwA_ugVP0pzJhoZMIn1QzydtHep82Ih3l7Y75a4Y9DjIwalkgctuInsHLdilN16b5Jbi2yFw8rhBEDAgW8xn03G9dtidWkpUiJHm0UynSpVbgY4eqTQBsWp2Xi0r9PB4XdJZRl3a8Dw4IFCnrinJT0anTGIlpRANhcq9WqydxrIf0tcJHUfvPppAbLFZk72QKGGkELqFSWVgG9ClwzGQIvMb4_fWiBs5iJNk2bbXDulzaIu4eBKPKtVpjLHGpE8AxcvdlH09DUumGkoF7Erue_CQZxTrENIUeV5_HtOi_tQFwkvbE0auS2gmRAlvl0wadvg_4byi7MsVpo8gg6zsLDe2sKUOJ27pfyGUhRju5G3fDtGnIaKXR57FvHBU7o84LFjdwl9y5Djj2CnwAb6s4P0pb6fKUwk_1B-BvppQ6lCJnGUUFr7h4zEm4HGEZZpdQmpHKvpSg_pBoWC-u68YOAKyD6blHJZKYgEn3fP3KTNtjDtHOUerp603dWX9uOUDw0Or5qtX37ckW6P3okeE01-t4nhxi_UdBCtV8iRFwm-P80afRpqNprxM1RPD0cKQAJGyLP3hMVzmbaoA-3K3sX6erlwSy6UJaVjR94SHK7k2IPN1cjaXyBgZHEXzUKGqdQSjzSiakhUpY7-V_Xin5euKun-BPUs0ny5oKELezslAOpZB8U41guD0SmHqpdbbRqEGTGcXwYpEn6LPBYrFWJ5aj3FuYtDzuT-FtMQHqi4CQiJrE2_MHFyy7p20UFYYv8M_5Tuz1sprr775EdPjEo9GuZjw1Chnr6z0fgvfpNDcSMRH_x_mtw3mtocQMyKc6qIfmKZdodfayRIXy0Angr8dmb00b0CnYX6jIs35J-Q-QPZnHPVS-avv76cWcnYBKIxxofEcUWZU4AUMW_MCTsy6UDWz3JNZuCx5rLvJ00EP0aCbd4-DKkMctFh61aIceBqm5JXZZBbhEdR92CBHd3g2dwvb6ikdhyzBHy3RX2Ey5r9Wy7VhOkS3bclLlcxoNpXO_rHN19Mt03z9Vjw4jGAl5Z90RcQwnWLidbyngXp8GYSTqTJmyJlqqQNeilBDF4SbAseMi6jRSO40t65-HrD7cDtkwapA9CyF9t7tYa-rBTCt2qUNKmgDShINprxneNUWpNV5NeY1gkyk657oe9xrJrTPIVJxZLgBGAAzjseK8TVhOE1j2VGzpQoPGudmmE7AICRUMdXbOTgGkalL4wxUmEiA; __Secure-next-auth.session-token.1=AZ4Lz-h7qBenBvoF0p2s5XqgfNo2TJ4mbezj445DF6m3g1nxb1Av-xGLpmHEGmQg-YiJbC3QTSFpqwYOj1AezJrB2orL-n_Gy6mBV_RCVoHPygfRFDbGJTCdAIYdopCxruYQ1ayYQ2LBObL3U4S5YJPyuR4_nCJD2Urwd1fT_SqRYAyhPhTxFsWhlsT8O0XCo2IPqBSdDk8WRRQ04Hv1kC_WymddKUOvf3XgKGPS2wERyuKb_g0HkQnQhZoDl_VlEPHx2JFW1grdoezIg7gGxXZ3UPmmw17VOCMxHGWLRkzns7dvXha0tK8dLKOHGP0lXHEYobKcNK5gzs5YOBgBg0fI0bqpejt8b7THEhjPmFmsossbB7zkY1eNfNuXk4myGrP0znAwq-tIGAPCCA4gN0m2LjvESfxKnUl0Xq8AyWldXZ1-L8_8o5g4CwMIJZ_9obL_FBZKzG1E_Qkvqb6Bar_aVkpQzuDhW0jzG2OezIyeqd7FP2lfsAb0r1Vx6cSi2hN8k-7bF_QSghBtg1njYEoCEkKdEjXpBj2Vgp3CjEyPbY8jUVQbkSGmW5n5iJbQxm0TBegdkaggKoFpVDXlWb-480I0JL5C73RHThxiG-2Uvy88coA2lpGP41b7xlvMxQMukte_G3--4fXulUr0vHJnei5JN_bJu3dXOtKEiStsk2n0xH59lTSdJo_VqyCYH0hlqV92NxgZ49kKTsuSFkVgCR57jbNLrHPQfox5H5otSRFifbeY2RsCCWijAxZQ_rzB8ZKEbbZzgLPlgiOWIJU5RIrMaCyodI505wie1SR0nRYcEannD2iJmy7IXDYRayb-753iTobfCASO6bHJIo5GKiw.Ei14mLJDBegtcPgS4qLuqQ',
            ),
            'method' => 'GET',
            'data_format' => 'body',
        ));

        $body = wp_remote_retrieve_body($response);

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return isset($responceData['accessToken']) ? $responceData['accessToken'] : null;
    }

    function return_generation($token, $generation_id)
    {
        $generations = $this->get_generations($token);

        foreach ($generations as $generation) {
            if ($generation['id'] === $generation_id && isset($generation['generated_images']) && !empty($generation['generated_images'])) {
                return $generation;
            }
        }

        return null;
    }

    public function retrieve_generation($token, $generation_id)
    {
        $timer = Loop::addPeriodicTimer(7, function () use ($token, $generation_id) {
            $latest_generation = $this->return_generation($token, $generation_id);

            if (!empty($latest_generation)) {
                Loop::stop();
                d($latest_generation);
            }
        });

        Loop::addTimer(28, function () use ($timer) {
            Loop::cancelTimer($timer);
        });
    }

    public function init_image_generating($token, $model_id = null)
    {
        $url = 'https://api.leonardo.ai/v1/graphql';

        if (!empty($model_id)) {
            $model_details = growtype_ai_get_model_details($model_id);

            $image_prompt = $model_details['prompt'];
            $prompt_variables = isset($model_details['settings']['prompt_variables']) ? $model_details['settings']['prompt_variables'] : null;
            $prompt_variables = !empty($prompt_variables) ? explode('|', $prompt_variables) : null;

            if (!empty($prompt_variables) && str_contains($image_prompt, '{prompt_variable}')) {
                $rendom_promp_variable_key = array_rand($prompt_variables, 1);

                $image_prompt = str_replace('{prompt_variable}', $prompt_variables[$rendom_promp_variable_key], $image_prompt);
            }

            $parameters = [
                'operationName' => 'CreateSDGenerationJob',
                'variables' => [
                    'arg1' => [
                        'prompt' => $image_prompt,
                        'negative_prompt' => $model_details['negative_prompt'],
                        'nsfw' => true,
                        'num_images' => 1,
                        'width' => (int)$model_details['settings']['image_width'],
                        'height' => (int)$model_details['settings']['image_height'],
                        'num_inference_steps' => (int)$model_details['settings']['num_inference_steps'],
                        'guidance_scale' => (int)$model_details['settings']['guidance_scale'],
                        'init_strength' => (int)$model_details['settings']['init_strength'],
                        'sd_version' => $model_details['settings']['sd_version'],
                        'modelId' => $model_details['settings']['model_id'],
                        'presetStyle' => $model_details['settings']['preset_style'],
                        'scheduler' => $model_details['settings']['scheduler'],
                        'leonardoMagic' => false,
                        'public' => false,
                        'tiling' => false,
                    ]
                ],
                'query' => 'mutation CreateSDGenerationJob($arg1: SDGenerationInput!) { sdGenerationJob(arg1: $arg1) { generationId __typename }}'
            ];

            $parameters = json_encode($parameters);
        } else {
            $parameters = '{
   "operationName":"CreateSDGenerationJob",
   "variables":{
      "arg1":{
         "prompt":"Sunset mountain",
         "negative_prompt":"Palm tree, beach, Sea,",
         "nsfw":true,
         "num_images":1,
         "width":512,
         "height":512,
         "num_inference_steps":30,
         "guidance_scale":7,
         "init_strength":0.5,
         "sd_version":"v1_5",
         "modelId":"fc42c4b3-1b19-44b7-b9fa-4d3d018af689",
         "presetStyle":"NONE",
         "scheduler":"EULER_DISCRETE",
         "leonardoMagic":false,
         "public":false,
         "tiling":false
      }
   },
   "query":"mutation CreateSDGenerationJob($arg1: SDGenerationInput!) {\n  sdGenerationJob(arg1: $arg1) {\n    generationId\n    __typename\n  }\n}"
}';
        }

        $response = wp_remote_post($url, array (
            'headers' => array (
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer ' . $token,
            ),
            'body' => $parameters,
            'method' => 'POST',
            'data_format' => 'body',
        ));

        $body = wp_remote_retrieve_body($response);

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return isset($responceData['data']['sdGenerationJob']['generationId']) ? $responceData['data']['sdGenerationJob']['generationId'] : null;
    }

    function get_user_details($token, $userSub)
    {
        $url = 'https://api.leonardo.ai/v1/graphql';

        $parameters = '{
   "operationName":"GetUserDetails",
   "variables":{
      "userSub":"' . $userSub . '"
   },
   "query":"query GetUserDetails($userSub: String) {\n  users(where: {user_details: {auth0Id: {_eq: $userSub}}}) {\n    id\n    username\n    user_details {\n      auth0Email\n      plan\n      paidTokens\n      subscriptionTokens\n      subscriptionModelTokens\n      subscriptionGptTokens\n      interests\n      showNsfw\n      __typename\n    }\n    __typename\n  }\n}"
}';

        $response = wp_remote_post($url, array (
            'headers' => array (
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer ' . $token,
            ),
            'body' => $parameters,
            'method' => 'POST',
            'data_format' => 'body',
        ));

        $body = wp_remote_retrieve_body($response);

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return $responceData;
    }

    function get_generations($token, $amount = 10, $user_nr = null)
    {
        $url = 'https://api.leonardo.ai/v1/graphql';

        $user_id = $this->get_user_credentials($user_nr)['user_id'];

        $parameters = '{
    "operationName": "GetAIGenerationFeed",
    "variables": {
        "where": {
            "userId": {
                "_eq": "' . $user_id . '"
            },
            "canvasRequest": {
                "_eq": false
            }
        },
        "userId": "' . $user_id . '"
    },
    "query": "query GetAIGenerationFeed($where: generations_bool_exp = {}, $userId: uuid!) {\n  generations(limit: ' . $amount . ', order_by: [{createdAt: desc}], where: $where) {\n    guidanceScale\n    inferenceSteps\n    modelId\n    scheduler\n    coreModel\n    sdVersion\n    prompt\n    negativePrompt\n    id\n    status\n    quantity\n    createdAt\n    imageHeight\n    imageWidth\n    presetStyle\n    sdVersion\n    seed\n    tiling\n    initStrength\n    user {\n      username\n      id\n      __typename\n    }\n    custom_model {\n      id\n      userId\n      name\n      modelHeight\n      modelWidth\n      __typename\n    }\n    init_image {\n      id\n      url\n      __typename\n    }\n    generated_images(order_by: [{url: desc}]) {\n      id\n      url\n      likeCount\n      generated_image_variation_generics(order_by: [{createdAt: desc}]) {\n        url\n        status\n        createdAt\n        id\n        transformType\n        __typename\n      }\n      user_liked_generated_images(limit: 1, where: {userId: {_eq: $userId}}) {\n        generatedImageId\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}"
}';

        $response = wp_remote_post($url, array (
            'headers' => array (
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer ' . $token,
            ),
            'body' => $parameters,
            'method' => 'POST',
            'data_format' => 'body',
        ));

        $body = wp_remote_retrieve_body($response);

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return isset($responceData['data']['generations']) ? $responceData['data']['generations'] : null;
    }

    function delete_generation($token, $id)
    {
        $url = 'https://api.leonardo.ai/v1/graphql';

        $parameters = '{"operationName":"DeleteGeneration","variables":{"id":"' . $id . '"},"query":"mutation DeleteGeneration($id: uuid!) {\n  delete_generations_by_pk(id: $id) {\n    id\n    __typename\n  }\n}"}';

        $response = wp_remote_post($url, array (
            'headers' => array (
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer ' . $token,
            ),
            'body' => $parameters,
            'method' => 'POST',
            'data_format' => 'body',
        ));

        $body = wp_remote_retrieve_body($response);

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return $responceData;
    }

    /**
     * @param $generations
     * @param $existing_model_id
     * @return void
     */
    function save_generations($generations, $existing_model_id = null)
    {
        /**
         * Group generations by unique key
         */
        $grouped_generations = [];
        foreach ($generations as $generation) {
            $unique_key = implode('-', [
                'grouped',
                $generation['modelId'],
                $generation['guidanceScale'],
                preg_replace('/\s+/', '_', trim(substr($generation['prompt'], 0, 100))),
                $generation['inferenceSteps'],
                $generation['scheduler'],
                $generation['coreModel'],
                $generation['sdVersion'],
                $generation['presetStyle'],
                $generation['tiling'],
            ]);

            $grouped_generations[trim($unique_key)][] = $generation;
        }

        foreach ($grouped_generations as $generations_group) {

            $reference_id = growtype_ai_generate_reference_id();

            if (!empty($existing_model_id)) {
                $model = growtype_ai_get_model_details($existing_model_id);
                $reference_id = $model['reference_id'];
            }

            foreach ($generations_group as $generation) {
                $image_folder = self::PROVIDER . '/' . $reference_id;
                $image_location = growtype_ai_get_images_saving_location();

                $existing_models = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::MODELS_TABLE, [
                    [
                        'key' => 'reference_id',
                        'values' => [$reference_id],
                    ]
                ]);

                if (empty($existing_models)) {
                    $model_id = Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::MODELS_TABLE, [
                        'prompt' => $generation['prompt'],
                        'negative_prompt' => !empty($generation['negativePrompt']) ? $generation['negativePrompt'] : 'watermark, watermarked, disfigured, ugly, grain, low resolution, deformed, blurred, bad anatomy, badly drawn face, extra limb, ugly, badly drawn arms, missing limb, floating limbs, detached limbs, deformed arms, out of focus, disgusting, badly drawn, disfigured, tile, badly drawn arms, badly drawn legs, badly drawn face, out of frame, extra limbs, deformed, body out of frame, grainy, clipped, bad proportion, cropped image, blur haze',
                        'reference_id' => $reference_id,
                        'provider' => self::PROVIDER,
                        'image_folder' => $image_folder
                    ]);

                    $model_settings = [
                        'model_id' => $generation['modelId'],
                        'guidance_scale' => $generation['guidanceScale'],
                        'inference_steps' => $generation['inferenceSteps'],
                        'scheduler' => $generation['scheduler'],
                        'core_model' => $generation['coreModel'],
                        'sd_version' => $generation['sdVersion'],
                        'tiling' => $generation['tiling'],
                        'init_strength' => $generation['initStrength'],
                        'image_width' => $generation['imageWidth'],
                        'image_height' => $generation['imageHeight'],
                        'num_inference_steps' => $generation['inferenceSteps'],
                        'preset_style' => $generation['presetStyle'],
                        'leonardo_magic' => isset($generation['leonardoMagic']) ? $generation['leonardoMagic'] : null,
                    ];

                    foreach ($model_settings as $key => $value) {

                        $existing_content = growtype_ai_get_model_single_setting($model_id, $key);

                        if (!empty($existing_content)) {
                            continue;
                        }

                        Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
                            'model_id' => $model_id,
                            'meta_key' => $key,
                            'meta_value' => $value
                        ]);
                    }

                    $openai_crud = new Openai_Crud();
                    $openai_crud->format_models(null, false, $model_id);
                } else {
                    $model_id = $existing_models[0]['id'];
                }

                foreach ($generation['generated_images'] as $image) {
                    $image['imageWidth'] = $generation['imageWidth'];
                    $image['imageHeight'] = $generation['imageHeight'];
                    $image['folder'] = $image_folder;
                    $image['location'] = $image_location;

                    $saved_image = Growtype_Ai_Crud::save_image($image);

                    if (empty($saved_image)) {
                        continue;
                    }

                    /**
                     * Assign image to model
                     */
                    Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::MODEL_IMAGE_TABLE, [
                        'model_id' => $model_id,
                        'image_id' => $saved_image['id']
                    ]);

                    /**
                     * Generate image content
                     */
                    $openai_crud = new Openai_Crud();
                    $openai_crud->format_image($saved_image['id']);

                    /**
                     * Update cloudinary image details
                     */
                    if ($image['location'] === 'cloudinary') {
                        $cloudinary_crud = new Cloudinary_Crud();
                        $cloudinary_crud->update_cloudinary_image_details($saved_image['id']);
                    }

                    sleep(2);
                }
            }
        }
    }

    function delete_external_generations($token, $generations)
    {
        foreach ($generations as $generation) {
            $this->delete_generation($token, $generation['id']);
        }
    }
}


