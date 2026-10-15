<?php
namespace RRZE\Cris;
defined('ABSPATH') || exit;

/**
 * Visualization class for rendering interactive maps and networks from data.fau.de
 * 
 * Renders visualizations via direct iframe src to data.fau.de/visualisation/{mapid}.html
 * Fetches metadata from CRIS API to determine visualization type and parameters
 */
class Visualization
{
    private string $vis_id;
    private string $size;
    private string $vis_type = '';
    private float $latitude = 0;
    private float $longitude = 0;
    private int $zoom = 4;
    private bool $fullscreenlink = false;
    public \WP_Error|null $error = null;

    private const SIZE_MAP = [
        's' => '400px',
        'm' => '600px',
        'l' => '900px',
    ];

    private const DEFAULT_SIZE = 'm';
    private const VIS_TYPE_MAP = 'visualisation_type_geographic';
    private const VIS_TYPE_NETWORK = 'visualisation_type_cooperationnetwork';

    /**
     * Constructor
     * 
     * @param string $vis_id Visualization ID (mapid)
     * @param string $page_lang Page language (unused, kept for backwards compatibility)
     * @param string $size Size option ('s', 'm', or 'l')
     * @param bool $fullscreenlink Enable fullscreen link button (default: false)
     */
    public function __construct(string $vis_id = '', string $page_lang = 'de', string $size = self::DEFAULT_SIZE, bool $fullscreenlink = false)
    {
        $this->vis_id = sanitize_text_field($vis_id);
        $this->size = isset(self::SIZE_MAP[$size]) ? $size : self::DEFAULT_SIZE;
        $this->fullscreenlink = $fullscreenlink;

        if (empty($this->vis_id)) {
            $this->error = new \WP_Error(
                'cris-vis-id-error',
                __('Visualization ID is required', 'fau-cris')
            );
            return;
        }

        // Fetch metadata from CRIS API
        $this->fetch_metadata();
    }

    /**
     * Fetch visualization metadata from CRIS API
     * 
     * @return void
     */
    private function fetch_metadata(): void
    {
        try {
            $webservice = new Webservice();
            $filter = new Filter([]);
            
            // Note: base_uri already includes "infoobject/", so we only need the action path
            $result = $webservice->get('get/visualization/' . $this->vis_id, $filter);
            
            if (is_wp_error($result)) {
                $this->error = $result;
                return;
            }

            // $result should be an array of infoObject elements
            if (!is_array($result)) {
                $result = [$result];
            }

            if (empty($result)) {
                $this->error = new \WP_Error(
                    'cris-vis-not-found',
                    __('Visualization not found', 'fau-cris')
                );
                return;
            }

            $infoObject = $result[0];

            // Parse attributes
            foreach ($infoObject->attribute as $attribute) {
                $attr_name = (string) $attribute['name'];
                $attr_value = (string) $attribute->data;
                $attr_additional = (string) $attribute->additionalInfo;

                switch (strtolower($attr_name)) {
                    case 'dynamicselector':
                        $this->vis_type = $attr_additional;
                        break;
                    case 'zoom':
                        // Extract zoom level from additionalInfo (e.g., "visualisation_zoom_3" -> 3)
                        if (preg_match('/zoom_(\d+)/', $attr_additional, $matches)) {
                            $this->zoom = (int) $matches[1];
                        }
                        break;
                    case 'originlatitude':
                        $this->latitude = (float) $attr_value;
                        break;
                    case 'originlongitude':
                        $this->longitude = (float) $attr_value;
                        break;
                }
            }

            // Validate that we got required metadata
            if (empty($this->vis_type)) {
                $this->error = new \WP_Error(
                    'cris-vis-type-error',
                    __('Visualization type could not be determined', 'fau-cris')
                );
            }
        } catch (\Exception $e) {
            $this->error = new \WP_Error(
                'cris-vis-fetch-error',
                /* translators: %s: error message returned by the CRIS webservice */
                sprintf(__('Error fetching visualization data: %s', 'fau-cris'), $e->getMessage())
            );
        }
    }

    /**
     * Render the visualization
     *
     * @return string HTML output or error message
     */
    public function display(): string
    {
        if ($this->error) {
            // Service-failure error codes get the friendly "unavailable" notice;
            // config-actionable codes (cris-vis-id-error, cris-vis-not-found,
            // cris-vis-type-error) keep their specific message.
            $serviceCodes = [
                'cris-xml-empty',
                'cris-xml-error',
                'cris-xml-parse',
                'cris-unexpected-root',
                'cris-fetch-failed',
                'cris-vis-fetch-error',
                'http_request_failed',
            ];
            if (in_array($this->error->get_error_code(), $serviceCodes, true)) {
                return '<div class="cris-error">'
                    . esc_html__(
                        'Die CRIS-Daten sind momentan nicht verfügbar. Bitte versuchen Sie es später erneut.',
                        'fau-cris'
                    )
                    . '</div>';
            }
            return sprintf(
                '<div class="cris-error">%s: %s</div>',
                esc_html__('Visualization Error', 'fau-cris'),
                esc_html($this->error->get_error_message())
            );
        }

        $html = $this->render_iframe();
        if ($this->fullscreenlink) {
            $html .= $this->render_fullscreen_button();
        }
        return $html;
    }

    /**
     * Render iframe with correct src based on visualization type
     * 
     * @return string iframe HTML
     */
    private function render_iframe(): string
    {
        $height = self::SIZE_MAP[$this->size];
        $url = $this->get_url();

        return sprintf(
            '<div class="cris-visualization cris-vis-size-%s">
                <iframe 
                    id="cris-vis-iframe-%s"
                    src="%s"
                    style="width: 100%%; height: %s; border: 2px solid #ddd; border-radius: 4px;"
                    title="%s"
                    loading="lazy"
                >
                </iframe>
            </div>',
            esc_attr($this->size),
            esc_attr($this->vis_id),
            esc_url($url),
            esc_attr($height),
            esc_attr(__('CRIS Visualization Map', 'fau-cris'))
        );
    }

    /**
     * Render fullscreen button (links directly to visualization URL)
     * 
     * @return string HTML with fullscreen button
     */
    private function render_fullscreen_button(): string
    {
        $url = $this->get_url();

        return sprintf(
            '<div class="cris-visualization-fullscreen-btn" style="text-align: center; margin-top: 15px;">
                <a 
                    href="%s"
                    target="_blank"
                    rel="noopener noreferrer"
                    style="display: inline-block; background: #002f6c; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; text-decoration: none;">
                    🖥️ %s
                </a>
            </div>',
            esc_url($url),
            esc_html__('Open Fullscreen Map', 'fau-cris')
        );
    }

    /**
     * Get the current size
     * 
     * @return string Size key (s, m, or l)
     */
    public function get_size(): string
    {
        return $this->size;
    }

    /**
     * Get visualization URL
     * 
     * @return string Visualization URL
     */
    public function get_url(): string
    {
        if ($this->error) {
            return '';
        }

        $base_url = 'https://data.fau.de/visualisation/' . $this->vis_id . '.html';

        // Add parameters for map type
        if ($this->vis_type === self::VIS_TYPE_MAP) {
            $base_url .= sprintf(
                '?isStart=0&zoom=%d&lat=%f&lng=%f',
                $this->zoom,
                $this->latitude,
                $this->longitude
            );
        }

        return $base_url;
    }

    /**
     * Get visualization type
     * 
     * @return string Visualization type
     */
    public function get_type(): string
    {
        return $this->vis_type;
    }

    /**
     * Check if visualization is a map
     * 
     * @return bool
     */
    public function is_map(): bool
    {
        return $this->vis_type === self::VIS_TYPE_MAP;
    }

    /**
     * Check if visualization is a network
     * 
     * @return bool
     */
    public function is_network(): bool
    {
        return $this->vis_type === self::VIS_TYPE_NETWORK;
    }

    /**
     * Check if visualization is valid (has no error)
     * 
     * @return bool
     */
    public function is_valid(): bool
    {
        return !$this->error;
    }
}
