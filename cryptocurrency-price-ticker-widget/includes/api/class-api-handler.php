<?php
if (!defined('ABSPATH')) {
    exit();
}
if (!class_exists('CCPW_api_data')) {
    class CCPW_api_data
    {
        use CCPW_Helper_Functions;

        /**
         * API endpoints for different services
         */
        const COINGECKO_API_ENDPOINT = 'https://api.coingecko.com/api/v3/';
        const COINPAPRIKA_API_ENDPOINT = 'https://api.coinpaprika.com/v1/tickers';
        const COINMARKETCAP_API_ENDPOINT = 'https://pro-api.coinmarketcap.com/';
        const COINCAP_API_ENDPOINT = 'https://api.coincap.io/v2/';
        const OPENEXCHANGERATE_API_ENDPOINT = 'https://openexchangerates.org/api/latest.json?app_id=';

        public function __construct()
        {
            // self::CMC_API_ENDPOINT = 'https://apiv3.coinexchangeprice.com/v3/';
        }

        /**
         * Fetches data from the CoinGecko API and saves it in the database.
         * MUST NOT CALL THIS FUNCTION DIRECTLY.
         */
        public function ccpw_get_coin_gecko_data()
        {
            $update_api_name = 'ccpw-active-api';
            $data_cache_name = 'ccpw-saved-coindata';

            // Retrieve transient data
            $activate_api = get_transient($update_api_name);
            $cache = get_transient($data_cache_name);

            // Get CoinGecko API key and cache time from settings
            $api_option = get_option("openexchange-api-settings");
            $coingecko_api_key = isset($api_option['coingecko_api']) ? $api_option['coingecko_api'] : "";
            $coingecko_api_cache_time = isset($api_option['select_cache_time']) ? (int) $api_option['select_cache_time'] : 10;

            // Check if user is authenticated
            if (!$this->ccpw_check_user()) {
                return;
            }

            // Avoid updating database if cache exists and the same API is requested
            if ($activate_api == 'CoinGecko' && false != $cache) {
                return;
            }

            // API URL for CoinGecko
            $api_url = self::COINGECKO_API_ENDPOINT . 'coins/markets?vs_currency=usd&order=market_cap_desc&per_page=250&page=1&sparkline=false&x_cg_demo_api_key=' . $coingecko_api_key;

            // Fetch data from CoinGecko API
            $request = wp_remote_get($api_url, array('timeout' => 120, 'sslverify' => false));

            // Check for WP error
            if (is_wp_error($request)) {
                return false; // Bail early
            }

            // Retrieve response body
            $body = wp_remote_retrieve_body($request);
            $coins = json_decode($body);
            $response = array();
            $coins_data = array();

            // Process coin data
            if (isset($coins) && $coins != "" && is_array($coins)) {
                // Track CoinGecko API hit
                $this->ccpw_track_coingecko_api_hit();

                foreach ($coins as $coin) {
                    // Skip coins with emoji in name, symbol, or coin_id
                    if ($this->contains_emoji($coin->name) || $this->contains_emoji($coin->symbol) || $this->contains_emoji($coin->id)) {
                        continue;
                    }
                    $response['coin_id'] = $coin->id;
                    $response['rank'] = $coin->market_cap_rank;
                    $response['name'] = $coin->name;
                    $response['symbol'] = strtoupper($coin->symbol);
                    $response['price'] = $this->ccpw_set_default_if_empty($coin->current_price, 0.00);
                    $response['percent_change_24h'] = $this->ccpw_set_default_if_empty($coin->price_change_percentage_24h, 0);
                    $response['market_cap'] = $this->ccpw_set_default_if_empty($coin->market_cap, 0);
                    $response['total_volume'] = $this->ccpw_set_default_if_empty($coin->total_volume);
                    $response['circulating_supply'] = $this->ccpw_set_default_if_empty($coin->circulating_supply);
                    $response['logo'] = substr($coin->image, strpos($coin->image, "images") + 7);
                    $coins_data[] = $response;

                    // Save data in chunks of 50 to avoid memory issues
                    if (count($coins_data) >= 50) {
                        $this->save_coin_data($coins_data);
                        $coins_data = array(); // Reset array for next chunk
                    }
                }

                // Save any remaining data that was not saved in the last chunk
                if (!empty($coins_data)) {
                    $this->save_coin_data($coins_data);
                }

                // Set transients for cache
                set_transient($data_cache_name, date('H:s:i'), $coingecko_api_cache_time * MINUTE_IN_SECONDS);
                set_transient($update_api_name, 'CoinGecko', 0);
            }
        }

        /**
         * Fetches data from the CoinMarketCap API and caches it for performance.
         */
        public function ccpw_get_coin_marketcap_data()
        {
            $update_api_name = 'ccpw-active-api';
            $data_cache_name = 'ccpw-saved-coindata';
            $page = 1;
            $numberoftokens = ($page == 1) ? $page : ($page - 1) * 200;

            // Retrieve transient data
            $activate_api = get_transient($update_api_name);
            $cache = get_transient($data_cache_name);

            // Get CoinMarketCap API key and cache time from settings
            $api_option = get_option("openexchange-api-settings");
            $cmc_api_key = isset($api_option['coinmarketcap_api']) ? $api_option['coinmarketcap_api'] : "";
            $cmc_api_cache_time = isset($api_option['select_cache_time']) ? (int) $api_option['select_cache_time'] : 10;
            
            // Check if user is authenticated
            if (!$this->ccpw_check_user()) {
                return;
            }
          
            // Avoid updating database if cache exists and the same API is requested
            if ($activate_api == 'CoinMarketCap' && false != $cache) {
                return;
            }
           
            // API URL for CoinMarketCap
            $api_url = self::COINMARKETCAP_API_ENDPOINT . 'v1/cryptocurrency/listings/latest?start=' . $numberoftokens . '&limit=200&CMC_PRO_API_KEY=' . $cmc_api_key;

            // Fetch data from CoinMarketCap API
            $request = wp_remote_get($api_url, array('timeout' => 120, 'sslverify' => false));
            if (is_wp_error($request)) {
                return false; // Bail early
            }

            $body = wp_remote_retrieve_body($request);
            $coins = json_decode($body, true);
            $response = array();
            $coin_data = array();
            if (isset($coins['data']) && $coins['data'] != "" && is_array($coins['data'])) {
                $this->ccpw_track_coingecko_api_hit();
                foreach ($coins['data'] as $coin) {
                    // Skip coins with emoji in name, symbol, or coin_id
                    if ($this->contains_emoji($coin['name']) || $this->contains_emoji($coin['symbol']) || $this->contains_emoji($coin['slug'])) {
                        continue;
                    }
                    $coin_id = $this->ccpw_cmc_coin_array($coin['slug']);
                    $cp = $this->ccwp_set_default_if_empty($coin['quote']['USD']['price'], 0.00);
                    $response['coin_id'] = $coin_id;
                    $response['name'] = $coin['name'];
                    $response['symbol'] = strtoupper($coin['symbol']);
                    $response['price'] = $cp;
                    $response['percent_change_24h'] = $this->ccpw_set_default_if_empty($coin['quote']['USD']['percent_change_24h']);
                    $response['market_cap'] = $this->ccpw_set_default_if_empty($coin['quote']['USD']['market_cap'], 0);
                    $response['total_volume'] = $this->ccpw_set_default_if_empty($coin['quote']['USD']['volume_24h']);
                    $response['circulating_supply'] = $this->ccpw_set_default_if_empty($coin['circulating_supply']);
                    $response['logo'] = null;
                    $extradata = array('cmc_id' => $coin['id'], 'rank' => $coin['cmc_rank']);
                    $response['extradata'] = maybe_serialize($extradata);
                    $response['last_updated'] = gmdate('Y-m-d h:i:s');
                    $coin_data[] = $response;

                    // Save data in chunks of 50 to avoid memory issues
                    if (count($coin_data) >= 50) {
                        $this->save_coin_data($coin_data);
                        $coin_data = array(); // Reset array for next chunk
                    }
                }

                // Save any remaining data that was not saved in the last chunk
                if (!empty($coin_data)) {
                    $this->save_coin_data($coin_data);
                }

                // Set transients for cache
                set_transient($data_cache_name, date('H:s:i'), $cmc_api_cache_time * MINUTE_IN_SECONDS);
                set_transient($update_api_name, 'CoinMarketCap', 0);
            }
        }

        /**
         * Fetches data from the CoinCap API and caches it for performance.
         */
        public function ccpw_get_coin_cap_data()
        {
            $update_api_name = 'ccpw-active-api';
            $data_cache_name = 'ccpw-saved-coindata';

            // Retrieve transient data
            $activate_api = get_transient($update_api_name);
            $cache = get_transient($data_cache_name);

            // Get CoinGecko API key and cache time from settings
            $api_option = get_option("openexchange-api-settings");
            $coincap_api_key = isset($api_option['coincap_api']) ? $api_option['coincap_api'] : "";
            $coincap_api_cache_time = isset($api_option['select_cache_time']) ? (int) $api_option['select_cache_time'] : 10;

            // Check if user is authenticated
            if (!$this->ccpw_check_user()) {
                return;
            }
            // Avoid updating database if cache exists and the same API is requested
            if ($activate_api == 'CoinCap' && false != $cache) {
                return;
            }
            // API URL for CoinGecko
            $api_url = self::COINCAP_API_ENDPOINT . 'assets?limit=250&apiKey=' . $coincap_api_key;

            // Fetch data from CoinGecko API
            $request = wp_remote_get($api_url, array('timeout' => 120, 'sslverify' => false));

            // Check for WP error
            if (is_wp_error($request)) {
                return false; // Bail early
            }

            // Retrieve response body
            $body = wp_remote_retrieve_body($request);
            $coins = json_decode($body,true);
            $response = array();
            $coins_data = array();
            
            // Process coin data
            if (isset($coins) && $coins != "" && is_array($coins)) {
                // Track CoinGecko API hit
                //$this->ccpw_track_coingecko_api_hit();

                foreach ($coins['data'] as $coin) {
                    // Skip coins with emoji in name, symbol, or coin_id
                    if ($this->contains_emoji($coin['name']) || $this->contains_emoji($coin['symbol']) || $this->contains_emoji($coin['id'])) {
                        continue;
                    }

                    $response['coin_id'] = $coin['id'];
                    $response['rank'] = $coin['rank'];
                    $response['name'] = $coin['name'];
                    $response['symbol'] = strtoupper($coin['symbol']);
                    $response['price'] = $this->ccpw_set_default_if_empty($coin['priceUsd'], 0.00);
                    $response['percent_change_24h'] = $this->ccpw_set_default_if_empty($coin['changePercent24Hr'], 0);
                    $response['market_cap'] = $this->ccpw_set_default_if_empty($coin['marketCapUsd'], 0);
                    $response['total_volume'] = "N/A";
                    $response['circulating_supply'] = $this->ccpw_set_default_if_empty($coin['supply']);
                    $response['logo'] = null;
                    $extradata = array('cc_id' => $coin['id'], 'rank' => $coin['rank'], 'sym'=>strtolower($coin['symbol']));
                    $response['extradata'] = maybe_serialize($extradata);
                    $coins_data[] = $response;
                    
                    // Save data in chunks of 50 to avoid memory issues
                    if (count($coins_data) >= 50) {
                        $this->save_coin_data($coins_data);
                        $coins_data = array(); // Reset array for next chunk
                    }
                }

                // Save any remaining data that was not saved in the last chunk
                if (!empty($coins_data)) {
                    $this->save_coin_data($coins_data);
                }
                
                // Set transients for cache
                set_transient($data_cache_name, date('H:s:i'), $coincap_api_cache_time * MINUTE_IN_SECONDS);
                set_transient($update_api_name, 'CoinCap', 0);
            }
        } //end of ccpw_get_coin_cap_data

        /**
         * Fetches data from the CoinPaprika API and caches it for performance.
         */
        public function ccpw_get_coin_paprika_data()
        {
            $update_api_name = 'ccpw-active-api';
            $data_cache_name = 'ccpw-saved-coindata';

            // Retrieve transient data
            $activate_api = get_transient($update_api_name);
            $cache = get_transient($data_cache_name);

            // Get cache time from API settings
            $api_option = get_option("openexchange-api-settings");
            $cache_time = isset($api_option['select_cache_time']) ? (int) $api_option['select_cache_time'] : 10;

            // Check if cache exists and the same API is requested, then return
            if ($activate_api == 'CoinPaprika' && false != $cache) {
                return;
            }

            // API URL for CoinPaprika
            $api_url = self::COINPAPRIKA_API_ENDPOINT;

            // Fetch data from API
            $request = wp_remote_get(
                $api_url,
                array(
                    'timeout' => 120,
                    'sslverify' => false,
                )
            );

            // Check for WP error
            if (is_wp_error($request)) {
                return false; // Bail early
            }

            // Retrieve response body
            $body = wp_remote_retrieve_body($request);
            $coin_info = json_decode($body, true);
            $response = array();
            $coins_data = array();

            // Limit the number of coins data to 250
            $coin_info = array_slice($coin_info, 0, 250);

            // Process coin data
            if (is_array($coin_info) && !empty($coin_info)) {
                foreach ($coin_info as $coin) {
                    // Skip coins with emoji in name, symbol, or coin_id
                    if ($this->contains_emoji($coin['name']) || $this->contains_emoji($coin['symbol']) || $this->contains_emoji($coin['id'])) {
                        continue;
                    }
                    $response['coin_id'] = $coin['id'];
                    $response['rank'] = $coin['rank'];
                    $response['name'] = $coin['name'];
                    $response['symbol'] = strtoupper($coin['symbol']);
                    $response['price'] = $this->ccpw_set_default_if_empty($coin['quotes']['USD']['price'], 0.00);
                    $response['percent_change_24h'] = $this->ccpw_set_default_if_empty($coin['quotes']['USD']['percent_change_24h']);
                    $response['market_cap'] = $this->ccpw_set_default_if_empty($coin['quotes']['USD']['market_cap'], 0);
                    //$response['circulating_supply'] = $this->ccpw_set_default_if_empty($coin['circulating_supply']);
                    $response['total_volume'] = 'N/A';
                    $response['logo'] = 'N/A';
                    $response['last_updated'] = gmdate('Y-m-d h:i:s');
                    $coins_data[] = $response;

                    // Save data in chunks of 50 to avoid memory issues
                    if (count($coins_data) >= 50) {
                        $this->save_coin_data($coins_data);
                        $coins_data = array(); // Reset array for next chunk
                    }
                }

                // Save any remaining data that was not saved in the last chunk
                if (!empty($coins_data)) {
                    $this->save_coin_data($coins_data);
                }

                // Set transients for cache
                set_transient($data_cache_name, date('H:s:i'), $cache_time * MINUTE_IN_SECONDS);
                set_transient($update_api_name, 'CoinPaprika', 0);
            }
        }

        /**
         * Save coin data to the database.
         *
         * @param array $coin_data The coin data to save.
         */
        private function save_coin_data($coin_data)
        {
            $DB = new ccpw_database();
            $DB->create_table();
            $DB->ccpw_insert($coin_data);
        }

        /**
         * Check if a string contains emoji.
         *
         * @param string $string The string to check.
         * @return bool True if the string contains emoji, false otherwise.
         */
        private function contains_emoji($string)
        {
            return preg_match('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', $string) > 0;
        }

        /**
         * Convert CoinMarketCap coin array.
         *
         * @param string $coin_id The coin ID.
         * @param bool $flip Whether to flip the array.
         * @return mixed The coin data.
         */
        public function ccpw_cmc_coin_array($coin_id, $flip = false)
        {
            $json_data = file_get_contents(CCPWF_DIR . 'assets/cmc-coins-ids.json');
            $coin_list = json_decode($json_data, true);

            if ($flip) {
                $coin_list = array_flip($coin_list);
            }

            return isset($coin_list[$coin_id]) ? $coin_list[$coin_id] : $coin_id;
        }

        /**
         * Set default value if empty.
         *
         * @param mixed $value The value to check.
         * @param mixed $default The default value.
         * @return mixed The value or the default value.
         */
        function ccwp_set_default_if_empty($value, $default = 'N/A')
        {
            return $value ? $value : $default;
        }

        /**
         * Retrieve USD conversions for cryptocurrencies.
         *
         * @param string $currency The currency code for which the conversion is requested.
         * @return mixed The USD conversions for the specified currency or false if not available.
         */
        public function ccpw_usd_conversions($currency)
        {
            $conversions = get_transient('cmc_usd_conversions');
            $conversions_option = get_option('cmc_usd_conversions');

            if (empty($conversions) || $conversions === "" || empty($conversions_option)) {
                $api_option = get_option("openexchange-api-settings");
                $api = !empty($api_option['openexchangerate_api']) ? $api_option['openexchangerate_api'] : "";

                if (empty($api)) {
                    if (!empty($conversions_option)) {
                        if ($currency == "all") {
                            return $conversions_option;
                        } else {
                            if (isset($conversions_option[$currency])) {
                                return $conversions_option[$currency];
                            }
                        }
                    }
                    return false;
                } else {
                    $request = wp_remote_get(self::OPENEXCHANGERATE_API_ENDPOINT . $api, array('timeout' => 120, 'sslverify' => true));
                }

                if (is_wp_error($request)) {
                    return false;
                }

                $currency_ids = array("USD", "AUD", "BRL", "CAD", "CZK", "DKK", "EUR", "HKD", "HUF", "ILS", "INR", "JPY", "MYR", "MXN", "NOK", "NZD", "PHP", "PLN", "GBP", "SEK", "CHF", "TWD", "THB", "TRY", "CNY", "KRW", "RUB", "SGD", "CLP", "IDR", "PKR", "ZAR");

                $body = wp_remote_retrieve_body($request);
                $conversion_data = json_decode($body);

                if (isset($conversion_data->rates)) {
                    $conversion_data = (array) $conversion_data->rates;
                } else {
                    $conversion_data = array();
                    if (!empty($conversions_option)) {
                        if ($currency == "all") {
                            return $conversions_option;
                        } else {
                            if (isset($conversions_option[$currency])) {
                                return $conversions_option[$currency];
                            }
                        }
                    }
                }

                if (is_array($conversion_data) && count($conversion_data) > 0) {
                    foreach ($conversion_data as $key => $currency_price) {
                        if (in_array($key, $currency_ids)) {
                            $conversions_option[$key] = $currency_price;
                        }
                    }

                    uksort($conversions_option, function ($key1, $key2) use ($currency_ids) {
                        return (array_search($key1, $currency_ids) > array_search($key2, $currency_ids)) ? 1 : -1;
                    });

                    update_option('cmc_usd_conversions', $conversions_option);
                    set_transient('cmc_usd_conversions', $conversions_option, 12 * HOUR_IN_SECONDS);
                }
            }

            if ($currency == "all") {
                return $conversions_option;
            } else {
                if (isset($conversions_option[$currency])) {
                    return $conversions_option[$currency];
                }
            }
        }
    }
}
