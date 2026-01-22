<?php

declare(strict_types=1);

namespace GRP\Core;

final readonly class SeoIntegrator
{
    /**
     * @return string|null 'rank_math', 'yoast', 'aioseo', 'seopress', 'tsf' or null
     */
    public function get_active_provider(): ?string
    {
        // Rank Math Logic
        if (defined('RANK_MATH_VERSION')) {
            $titles = get_option('rank_math_titles');
            if (!empty($titles['knowledge_graph_type']) &&
                ($titles['knowledge_graph_type'] === 'company' || $titles['knowledge_graph_type'] === 'person')) {
                return 'rank_math';
            }
        }

        // Yoast Logic (Basic)
        if (defined('WPSEO_VERSION')) {
            $titles = get_option('wpseo_titles');
            if (!empty($titles['company_or_person']) && !empty($titles['company_name'])) {
                return 'yoast';
            }
        }

        // All in One SEO (AIOSEO)
        if (defined('AIOSEO_VERSION')) {
            $aioseo = get_option('aioseo_options');
            if (!empty($aioseo['localBusiness']['locations']['business']['name']) ||
                !empty($aioseo['searchAppearance']['global']['schema']['organizationName'])) {
                return 'aioseo';
            }
        }

        // SEOPress
        if (defined('SEOPRESS_VERSION')) {
            $seopress = get_option('seopress_titles_option_name');
            if (!empty($seopress['seopress_titles_knowledge_type']) &&
                ($seopress['seopress_titles_knowledge_type'] === 'org' || $seopress['seopress_titles_knowledge_type'] === 'person')) {
                return 'seopress';
            }
        }

        // The SEO Framework (TSF)
        if (defined('THE_SEO_FRAMEWORK_VERSION')) {
            $tsf = get_option('autodescription-site-settings');
            if (!empty($tsf['knowledge_name'])) {
                return 'tsf';
            }
        }

        return null;
    }

    /**
     * @return array{
     *     "name": string,
     *     "phone": string,
     *     "price_range": string,
     *     "address": string,
     *     "lat": string,
     *     "lng": string
     * }
     */
    public function get_local_data(): array
    {
        $provider = $this->get_active_provider();
        $data = [
            'name'        => '',
            'phone'       => '',
            'price_range' => '',
            'address'     => '',
            'lat'         => '',
            'lng'         => '',
        ];

        if ($provider === 'rank_math') {
            $rm = get_option('rank_math_titles');

            $data['name'] = $rm['knowledge_graph_name'] ?? '';
            $data['phone'] = $rm['phone'] ?? '';
            $data['price_range'] = $rm['price_range'] ?? '';

            if (!empty($rm['local_address']) && is_array($rm['local_address'])) {
                $addr_parts = array_filter([
                    $rm['local_address']['streetAddress'] ?? '',
                    $rm['local_address']['addressLocality'] ?? '',
                    $rm['local_address']['addressRegion'] ?? '',
                    $rm['local_address']['postalCode'] ?? '',
                    $rm['local_address']['addressCountry'] ?? ''
                ]);
                $data['address'] = implode(', ', $addr_parts);
            }

            if (!empty($rm['geo'])) {
                $parts = explode(',', $rm['geo']);
                if (count($parts) === 2) {
                    $data['lat'] = trim($parts[0]);
                    $data['lng'] = trim($parts[1]);
                }
            }
        } elseif ($provider === 'yoast') {
            $yoast = get_option('wpseo_titles');
            $data['name'] = $yoast['company_name'] ?? '';
        } elseif ($provider === 'aioseo') {
            $aio = get_option('aioseo_options');
            $local = $aio['localBusiness']['locations']['business'] ?? [];
            $data['name'] = !empty($local['name']) ? $local['name'] : ($aio['searchAppearance']['global']['schema']['organizationName'] ?? '');
            $data['phone'] = $local['contact']['phone'] ?? ($aio['searchAppearance']['global']['schema']['phone'] ?? '');
            $data['price_range'] = $local['payment']['priceRange'] ?? '';

            if (!empty($local['address'])) {
                $addr_parts = array_filter([
                    $local['address']['streetLine1'] ?? '',
                    $local['address']['city'] ?? '',
                    $local['address']['state'] ?? '',
                    $local['address']['zipCode'] ?? '',
                    $local['address']['country'] ?? ''
                ]);
                $data['address'] = implode(', ', $addr_parts);
            }

            $data['lat'] = $local['maps']['latitude'] ?? '';
            $data['lng'] = $local['maps']['longitude'] ?? '';
        } elseif ($provider === 'seopress') {
            $seopress = get_option('seopress_titles_option_name');
            $data['name']  = $seopress['seopress_titles_knowledge_name'] ?? '';
            $data['phone'] = $seopress['seopress_titles_knowledge_phone'] ?? '';
            $seopress_pro = get_option('seopress_pro_option_name');

            if (!empty($seopress_pro['seopress_local_business_street_address'])) {
                $data['address'] = $seopress_pro['seopress_local_business_street_address'];
            }

            if (!empty($seopress_pro['seopress_local_business_lat'])) {
                $data['lat'] = $seopress_pro['seopress_local_business_lat'];
                $data['lng'] = $seopress_pro['seopress_local_business_lon'];
            }
        } elseif ($provider === 'tsf') {
            $tsf = get_option('autodescription-site-settings');
            $data['name'] = $tsf['knowledge_name'] ?? '';
        }

        return $data;
    }
}
