<?php
/*
 * Plugin Name: AI Review Auto-Reply for WooCommerce
 * Description: Automatically replies to WooCommerce product reviews using AI, trained from product data & your previous reply tone.
 * Version: 1.2.0
 * Author: Pigment.Dev
 * Author URI: https://pigment.dev/
 * Plugin URI: https://pigment.dev/
 * Contributors: pigmentdev
 * Tags: woocommerce, ai, review, auto-reply
 * Requires PHP: 7.4
 * Requires at least: 5.0
 * Tested up to: 6.8
 * WC requires at least: 5.0
 * WC tested up to: 10.1
 * Text Domain: ai-review-auto-reply
 * Domain Path: /languages
 * Copyright: (c) Pigment.Dev, All rights reserved.
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * @Credit: AmirhpCom, PigmentDev, Written with help of ChatGPT-5 for fun purposes
 * @Last modified by: amirhp-com <its@amirhp.com>
 * @Last modified time: 2025/09/12 12:29:37
*/
defined("ABSPATH") or die("<h2>Unauthorized Access!</h2><hr><small>AI Review Auto-Reply for WooCommerce :: Developed by <a href='https://pigment.dev'>Pigment.Dev</a></small>");

if (!class_exists("PD_AI_Review_Reply")) {

  class PD_AI_Review_Reply {

    /** @var string Option key for settings */
    private $option_key = "pd_ai_review_reply_options";

    /** @var array Plugin settings cached */
    private $opts = array();

    /**
     * Boot the plugin.
     */
    public function __construct() {
      // Load settings on init
      add_action("init", array($this, "load_options"));

      // add woocommerce hpos support
      add_action("before_woocommerce_init", function () {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
          \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
      });

      // Admin menu & settings
      add_action("admin_menu", array($this, "register_settings_page"));
      add_action("admin_init", array($this, "register_settings"));

      // Auto-reply when a new product review is approved
      add_action("comment_post", array($this, "maybe_queue_ai_reply"), 10, 3);
      add_action("transition_comment_status", array($this, "on_comment_status_change"), 10, 3);

      // Manual action to (re)process via bulk or single (screen action)
      add_action("admin_post_pd_ai_reply_review", array($this, "handle_manual_reply"));

      // AJAX test prompt (admin only)
      add_action("wp_ajax_pd_ai_test_prompt", array($this, "ajax_test_prompt"));
    }

    /**
     * Load saved options into memory.
     */
    public function load_options() {
      $defaults = array(
        "debug"          => "no",              // enable debug
        "enabled"        => "yes",             // Auto-reply toggle
        "provider_url"   => "",                // AI endpoint (e.g., https://api.openai.com/v1/chat/completions)
        "api_key"        => "",                // Secret API key
        "api_key_header" => "Authorization",   // Header key
        "api_key_value"  => "Bearer %s",       // Header value format
        "model"          => "gpt-4o-mini",     // Model name (free text)
        "temperature"    => "0.3",
        "max_tokens"     => "400",
        "examples"       => 5,                 // How many previous replies to learn tone from
        "guidelines"     => "",                // Site-specific style/training text
        "role_author_id" => 0,                 // User ID to post replies as (0 => current admin_email identity)
        "mock_mode"      => "no",              // If yes, generates a local template reply (no API call)
        "delay_seconds"  => 10,                // Delay for replying after approval
      );
      $this->opts = wp_parse_args((array) get_option($this->option_key, array()), $defaults);

      // manually create ai review if requested (admin only)
      if (current_user_can("manage_options") && is_admin() && isset($_GET["gen_ai_review"]) && !empty($_GET["gen_ai_review"])) {
        $gen_ai_review = sanitize_text_field(trim($_GET["gen_ai_review"]));
        $res = @$this->dispatch_reply_now_or_later($gen_ai_review);
        wp_die("AI Reply generated: " . (is_string($res) ? $res : "Done") . "<br><a href='" . esc_url(admin_url("edit-comments.php")) . "'>&laquo; Back to reviews</a>");
      }

      // add review reply action to comments list
      add_filter("comment_row_actions", function ($actions, $comment) {
        if ("product" === get_post_type($comment->comment_post_ID) && (int) $comment->comment_parent === 0) {
          $url = wp_nonce_url(admin_url("admin-post.php?action=pd_ai_reply_review&comment_id=" . (int) $comment->comment_ID), "pd_ai_reply_review");
          $actions["pd_ai_reply"] = '<a href="' . esc_url($url) . '">' . __("Generate AI Reply", "pd-ai-review-reply") . '</a>';
        }
        return $actions;
      }, 10, 2);
    }

    /**
     * Register admin settings page.
     */
    public function register_settings_page() {
      add_menu_page(
        __("AI Review Reply", "pd-ai-review-reply"),
        __("AI Review Reply", "pd-ai-review-reply"),
        "manage_woocommerce",
        "pd-ai-review-reply",
        array($this, "render_settings_page"),
        "dashicons-format-chat",
        56
      );
    }

    /**
     * Register settings fields.
     */
    public function register_settings() {
      register_setting($this->option_key, $this->option_key);

      add_settings_section("pd_ai_main", __("General", "pd-ai-review-reply"), "__return_false", $this->option_key);

      $fields = array(
        "enabled"        => __("Enable auto-replies", "pd-ai-review-reply"),
        "debug"          => __("Enable Debug", "pd-ai-review-reply"),
        "provider_url"   => __("Provider endpoint URL", "pd-ai-review-reply"),
        "api_key"        => __("API key", "pd-ai-review-reply"),
        "api_key_header" => __("API key header", "pd-ai-review-reply"),
        "api_key_value"  => __("API key header value (use %s placeholder)", "pd-ai-review-reply"),
        "model"          => __("Model", "pd-ai-review-reply"),
        "temperature"    => __("Temperature", "pd-ai-review-reply"),
        "max_tokens"     => __("Max tokens", "pd-ai-review-reply"),
        "examples"       => __("Previous replies to learn tone from", "pd-ai-review-reply"),
        "guidelines"     => __("Guidelines / brand voice (optional)", "pd-ai-review-reply"),
        "role_author_id" => __("Reply as User ID (0=auto)", "pd-ai-review-reply"),
        "mock_mode"      => __("Mock mode (no API call)", "pd-ai-review-reply"),
        "delay_seconds"  => __("Reply delay (seconds)", "pd-ai-review-reply"),
      );

      foreach ($fields as $key => $label) {
        add_settings_field($key, $label, array($this, "field_" . $key), $this->option_key, "pd_ai_main");
      }
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
      if (! current_user_can("manage_woocommerce")) {
        return;
      }
?>
      <div class="wrap">
        <h1><?php esc_html_e("AI Review Auto-Reply for WooCommerce", "pd-ai-review-reply"); ?></h1>
        <form method="post" action="options.php">
          <?php
          settings_fields($this->option_key);
          do_settings_sections($this->option_key);
          submit_button();
          ?>
        </form>

        <hr>
        <h2><?php esc_html_e("Test Prompt (admin only)", "pd-ai-review-reply"); ?></h2>
        <p><?php esc_html_e("Enter a product ID and a sample customer review text. The system will build the prompt and either call the provider (or mock) and show the generated reply.", "pd-ai-review-reply"); ?></p>
        <table class="form-table">
          <tr>
            <th><?php esc_html_e("Product ID", "pd-ai-review-reply"); ?></th>
            <td><input type="number" id="pd_product_id" value="" class="regular-text"></td>
          </tr>
          <tr>
            <th><?php esc_html_e("Sample Review", "pd-ai-review-reply"); ?></th>
            <td><textarea id="pd_sample_review" class="large-text" rows="5"></textarea></td>
          </tr>
        </table>
        <p>
          <button type="button" class="button button-primary" id="pd_run_test"><?php esc_html_e("Run Test", "pd-ai-review-reply"); ?></button>
        </p>
        <pre id="pd_test_result" style="background:#111;color:#0f0;padding:12px;border-radius:6px;overflow:auto"></pre>

        <script>
          (function($) {
            $("#pd_run_test").on("click", function() {
              $("#pd_test_result").text("...");
              $.post(ajaxurl, {
                action: "pd_ai_test_prompt",
                product_id: $("#pd_product_id").val(),
                review: $("#pd_sample_review").val(),
                _wpnonce: "<?php echo esc_js(wp_create_nonce("pd_ai_test")); ?>"
              }, function(resp) {
                console.log(resp);
                $("#pd_test_result").html(
                  "<strong>Generated Reply:</strong> " + (resp?.data?.reply || "") +
                  "<hr><strong>Sent Prompt:</strong> " + (resp?.data?.prompt || "")
                );
              });
            });
          })(jQuery);
        </script>
      </div>
    <?php
    }

    /* =========================
     * Settings Fields Renderers
     * ========================= */

    public function field_enabled() {
      $v = $this->opts["enabled"];
    ?>
      <label><input type="checkbox" name="<?php echo esc_attr($this->option_key); ?>[enabled]" value="yes" <?php checked($v, "yes"); ?>> <?php esc_html_e("Enable", "pd-ai-review-reply"); ?></label>
    <?php
    }
    public function field_debug() {
      $v = $this->opts["debug"];
    ?>
      <label><input type="checkbox" name="<?php echo esc_attr($this->option_key); ?>[debug]" value="yes" <?php checked($v, "yes"); ?>> <?php esc_html_e("Enable Debug responses", "pd-ai-review-reply"); ?></label>
    <?php
    }
    public function field_provider_url() {
      $this->text("provider_url");
    }
    public function field_api_key() {
      $this->password("api_key");
    }
    public function field_api_key_header() {
      $this->text("api_key_header", "Authorization");
    }
    public function field_api_key_value() {
      $this->text("api_key_value", "Bearer %s");
    }
    public function field_model() {
      $this->text("model", "gpt-4o-mini");
    }
    public function field_temperature() {
      $this->number("temperature", 0.0, 2.0, "0.1");
    }
    public function field_max_tokens() {
      $this->number("max_tokens", 10, 4000, "10");
    }
    public function field_examples() {
      $this->number("examples", 0, 20, "1");
    }
    public function field_role_author_id() {
      $this->number("role_author_id", 0, 999999, "1");
    }
    public function field_mock_mode() {
      $v = $this->opts["mock_mode"];
    ?>
      <label><input type="checkbox" name="<?php echo esc_attr($this->option_key); ?>[mock_mode]" value="yes" <?php checked($v, "yes"); ?>> <?php esc_html_e("Enable mock (no external calls)", "pd-ai-review-reply"); ?></label>
    <?php
    }
    public function field_guidelines() {
      $v = $this->opts["guidelines"];
    ?>
      <textarea name="<?php echo esc_attr($this->option_key); ?>[guidelines]" class="large-text" rows="6" placeholder="<?php esc_attr_e("e.g. brand tone, politeness level, return policy summary, etc.", "pd-ai-review-reply"); ?>"><?php echo esc_textarea($v); ?></textarea>
<?php
    }
    public function field_delay_seconds() {
      $this->number("delay_seconds", 0, 3600, "1");
    }

    private function text($key, $placeholder = "") {
      $v = $this->opts[$key];
      printf('<input type="text" name="%1$s[%2$s]" value="%3$s" class="regular-text" placeholder="%4$s">', esc_attr($this->option_key), esc_attr($key), esc_attr($v), esc_attr($placeholder));
    }
    private function password($key) {
      $v = $this->opts[$key];
      printf('<input type="password" name="%1$s[%2$s]" value="%3$s" class="regular-text">', esc_attr($this->option_key), esc_attr($key), esc_attr($v));
    }
    private function number($key, $min, $max, $step) {
      $v = $this->opts[$key];
      printf('<input type="number" name="%1$s[%2$s]" value="%3$s" min="%4$s" max="%5$s" step="%6$s" class="small-text">', esc_attr($this->option_key), esc_attr($key), esc_attr($v), esc_attr($min), esc_attr($max), esc_attr($step));
    }

    /* =========================
     * Auto-Reply Triggers
     * ========================= */

    /**
     * When a comment is posted, schedule a reply if approved immediately.
     *
     * @param int   $comment_ID
     * @param int   $comment_approved 1 if approved
     * @param array $commentdata
     */
    public function maybe_queue_ai_reply($comment_ID, $comment_approved, $commentdata) {
      if ("yes" !== $this->opts["enabled"]) {
        return;
      }
      if (empty($comment_ID)) {
        return;
      }

      $comment = get_comment($comment_ID);
      if (! $comment) {
        return;
      }

      // Only for product reviews
      $post_id = (int) $comment->comment_post_ID;
      if ($post_id <= 0 || "product" !== get_post_type($post_id)) {
        return;
      }

      // Only for top-level customer reviews (not replies)
      if ((int) $comment->comment_parent !== 0) {
        return;
      }

      if (1 === (int) $comment_approved) {
        $this->dispatch_reply_now_or_later($comment_ID);
      }
    }

    /**
     * Handle status transitions: when a pending review gets approved.
     *
     * @param string $new_status
     * @param string $old_status
     * @param object $comment
     */
    public function on_comment_status_change($new_status, $old_status, $comment) {
      if ("yes" !== $this->opts["enabled"]) {
        return;
      }
      if (! $comment instanceof WP_Comment) {
        return;
      }

      $post_id = (int) $comment->comment_post_ID;
      if ($post_id <= 0 || "product" !== get_post_type($post_id)) {
        return;
      }
      if ((int) $comment->comment_parent !== 0) {
        return;
      }

      if ("approved" === $new_status) {
        $this->dispatch_reply_now_or_later($comment->comment_ID);
      }
    }

    /**
     * Dispatch immediate or delayed reply job.
     *
     * @param int $comment_ID
     */
    private function dispatch_reply_now_or_later($comment_ID) {
      $delay = max(0, (int) $this->opts["delay_seconds"]);
      if ($delay > 0) {
        wp_schedule_single_event(time() + $delay, "pd_ai_reply_event", array((int) $comment_ID));
        add_action("pd_ai_reply_event", array($this, "generate_and_post_reply"), 10, 1);
      } else {
        return @$this->generate_and_post_reply((int) $comment_ID);
      }
    }

    /**
     * Admin manual handler (single or bulk).
     */
    public function handle_manual_reply() {
      if (! current_user_can("manage_woocommerce")) {
        wp_die("Forbidden");
      }
      $comment_id = isset($_GET["comment_id"]) ? (int) $_GET["comment_id"] : 0;
      if ($comment_id > 0) {
        @$this->generate_and_post_reply($comment_id);
      }
      wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url("edit-comments.php"));
      exit;
    }

    /**
     * Build the prompt, call the provider (or mock), and post the reply.
     *
     * @param int $comment_ID
     */
    public function generate_and_post_reply($comment_ID) {
      $comment = get_comment($comment_ID);
      if (! $comment) return;

      $post_id = (int) $comment->comment_post_ID;
      if ("product" !== get_post_type($post_id)) return;

      // Prevent duplicate reply if already replied by us
      if ($this->already_replied($comment_ID)) return "ALREADY REPLIED";

      $product = function_exists("wc_get_product") ? wc_get_product($post_id) : null;

      $context = $this->build_context($product, $comment);
      $prompt  = $this->build_prompt($context);

      $reply_text = ("yes" === $this->opts["mock_mode"])
        ? $this->mock_generate($context)
        : $this->call_ai_provider($prompt);

      $reply_text = $this->sanitize_reply($reply_text, $product);

      if (!empty($reply_text)) {
        $author_id = (int) $this->opts["role_author_id"];
        if ($author_id <= 0) {
          $admin = get_user_by("email", get_option("admin_email"));
          $author_id = $admin ? (int) $admin->ID : 0;
        }
        $data = array(
          "comment_post_ID"      => $post_id,
          "comment_content"      => $reply_text,
          "comment_parent"       => $comment_ID,
          "comment_approved"     => 1,
          "user_id"              => $author_id,
          "comment_author"       => $author_id > 0 ? get_the_author_meta("display_name", $author_id) : get_bloginfo("name"),
          "comment_author_email" => $author_id > 0 ? get_the_author_meta("user_email", $author_id) : get_option("admin_email"),
        );
        @wp_insert_comment(wp_slash($data));
      }
      return $reply_text;
    }

    /**
     * AJAX tester for building prompt and getting a response.
     */
    public function ajax_test_prompt() {
      check_ajax_referer("pd_ai_test");
      if (! current_user_can("manage_woocommerce")) {
        wp_send_json_error("forbidden");
      }

      $product_id = isset($_POST["product_id"]) ? (int) $_POST["product_id"] : 0;
      $review     = isset($_POST["review"]) ? wp_kses_post(wp_unslash($_POST["review"])) : "";

      $product = $product_id ? wc_get_product($product_id) : null;
      $dummy_comment = (object) array(
        "comment_content" => $review,
        "comment_author"  => "Test Customer",
        "comment_author_email" => "test@example.com",
      );

      $context = $this->build_context($product, $dummy_comment);
      $prompt  = $this->build_prompt($context);

      $reply = ("yes" === $this->opts["mock_mode"])
        ? $this->mock_generate($context)
        : $this->call_ai_provider($prompt);

      wp_send_json_success(array(
        "prompt" => $prompt,
        "reply"  => $reply,
      ));
    }

    /* =========================
     * Core Helpers
     * ========================= */

    /**
     * Check if we already replied to this review.
     *
     * @param int $comment_ID
     * @return bool
     */
    private function already_replied($comment_ID) {
      $children = get_comments(array(
        "parent" => $comment_ID,
        "status" => "approve",
        "number" => 1,
        "count"  => true,
      ));
      return ((int) $children > 0);
    }

    /**
     * Build AI context from product data, guidelines, and tone examples.
     *
     * @param WC_Product|null $product
     * @param WP_Comment|object $comment
     * @return array
     */
    private function build_context($product, $comment) {
      $product_data = array(
        "title"       => $product ? $product->get_name() : "",
        "sku"         => $product && method_exists($product, "get_sku") ? $product->get_sku() : "",
        "short_desc"  => $product ? wp_strip_all_tags($product->get_short_description()) : "",
        "desc"        => $product ? wp_strip_all_tags($product->get_description()) : "",
        "price"       => $product ? $product->get_price() : "",
        "attributes"  => array(),
        "permalink"   => $product ? get_permalink($product->get_id()) : "",
      );

      if ($product && method_exists($product, "get_attributes")) {
        foreach ($product->get_attributes() as $key => $attr) {
          // $product_data["attributes"][] = is_object($attr) && method_exists($attr, "get_name") ? $attr->get_name() . ": " . wc_get_formatted_variation(array($key => $attr->get_data())) : $key;
          if (is_object($attr) && method_exists($attr, "get_name") && method_exists($attr, "get_options")) {
            $attribute_name = $attr->get_name();
            $attribute_values = $attr->get_options();
            if (is_array($attribute_values)) {
              $attribute_values = implode(', ', $attribute_values);
            }
            $product_data["attributes"][] = $attribute_name . ": " . $attribute_values;
          } else {
            $product_data["attributes"][] = $key;
          }
        }
      }

      $guidelines = trim((string) $this->opts["guidelines"]);

      $examples = $this->collect_previous_replies($product ? $product->get_id() : 0, (int) $this->opts["examples"]);

      return array(
        "site_name" => get_bloginfo("name"),
        "review"    => wp_strip_all_tags($comment->comment_content),
        "customer"  => isset($comment->comment_author) ? $comment->comment_author : "",
        "product"   => $product_data,
        "guidelines" => $guidelines,
        "examples"  => $examples,
        "policies"  => $this->summarize_basic_policy(), // optional stub
      );
    }

    /**
     * Build a single prompt string for chat/completions providers.
     *
     * @param array $ctx
     * @return string
     */
    private function build_prompt($ctx) {
      $lines = array();

      $lines[] = "You are a helpful support assistant for a WooCommerce shop named \"{$ctx["site_name"]}\".";
      $lines[] = "Your task: reply to a product review as the shop in a warm, concise, and brand-aligned tone.";
      if (! empty($ctx["guidelines"])) {
        $lines[] = "Brand voice & guidelines:\n" . $ctx["guidelines"];
      }
      if (! empty($ctx["examples"])) {
        $lines[] = "Here are previous replies from this shop. Mirror their style and phrasing:";
        foreach ($ctx["examples"] as $ex) {
          $lines[] = "- Example reply: " . $ex;
        }
      }

      $p = $ctx["product"];
      $lines[] = "Product context:";
      $lines[] = "- Title: {$p["title"]}";
      if ($p["sku"]) {
        $lines[] = "- SKU: {$p["sku"]}";
      }
      if ($p["price"]) {
        $lines[] = "- Price: {$p["price"]}";
      }
      if ($p["short_desc"]) {
        $lines[] = "- Short desc: {$p["short_desc"]}";
      }
      if ($p["desc"]) {
        $lines[] = "- Details: {$p["desc"]}";
      }
      if (! empty($p["attributes"])) {
        $lines[] = "- Attributes: " . implode(", ", array_filter($p["attributes"]));
      }

      if (! empty($ctx["policies"])) {
        $lines[] = "Policy notes (if relevant): " . $ctx["policies"];
      }

      $lines[] = "Customer review (from {$ctx["customer"]}):\n\"{$ctx["review"]}\"";

      $lines[] = "Instructions:";
      $lines[] = "- Keep it friendly, helpful, and on-brand.";
      $lines[] = "- If the review is positive, express gratitude.";
      $lines[] = "- If there's an issue, apologize briefly and provide a clear next step (email/WhatsApp/order number/help article).";
      $lines[] = "- Avoid revealing internal policies not public on the site.";
      $lines[] = "- Write in the same language as the review.";
      $lines[] = "- One concise paragraph (2–5 sentences).";

      return implode("\n", $lines);
    }

    /**
     * Collect previous approved replies to learn tone.
     *
     * @param int $product_id
     * @param int $limit
     * @return array
     */
    private function collect_previous_replies($product_id, $limit) {
      if ($product_id <= 0 || $limit <= 0) {
        return array();
      }

      $args = array(
        "post_id" => $product_id,
        "status"  => "approve",
        "parent__not_in" => array(0), // only replies
        "number"  => $limit,
        "orderby" => "comment_date_gmt",
        "order"   => "DESC",
      );
      $replies = get_comments($args);
      $texts = array();
      foreach ($replies as $c) {
        $texts[] = wp_strip_all_tags($c->comment_content);
      }
      return $texts;
    }

    /**
     * Minimal policy summary stub (customize or feed from a page).
     *
     * @return string
     */
    private function summarize_basic_policy() {
      // You can map from WooCommerce settings or a specific page/content.
      return "";
    }

    /**
     * Call the AI provider using wp_remote_post with standard JSON schema.
     *
     * @param string $prompt
     * @return string
     */
    private function call_ai_provider($prompt) {
      $url = trim($this->opts["provider_url"]);
      $key = trim($this->opts["api_key"]);

      if (empty($url) || empty($key)) {
        return "EMPTY URL or API KEY";
      }

      $headers = array(
        "Content-Type" => "application/json",
      );

      $api_header_key   = trim($this->opts["api_key_header"]);
      $api_header_value = sprintf($this->opts["api_key_value"], $key);
      if ($api_header_key) {
        $headers[$api_header_key] = $api_header_value;
      }

      // Generic Chat Completions payload (compatible with many providers)
      $body = array(
        "model"       => $this->opts["model"],
        "temperature" => (float) $this->opts["temperature"],
        "max_tokens"  => (int) $this->opts["max_tokens"],
        "messages"    => array(
          array("role" => "system", "content" => "You are a customer support assistant for an online store."),
          array("role" => "user",   "content" => $prompt),
        ),
      );

      $res = wp_remote_post($url, array(
        "headers" => $headers,
        "timeout" => 30,
        "body"    => wp_json_encode($body),
      ));

      if (is_wp_error($res)) {
        return "ERROR: " . $res->get_error_message();
      }

      $code = (int) wp_remote_retrieve_response_code($res);
      $json = json_decode(wp_remote_retrieve_body($res), true);

      // Try common schemas
      if (200 === $code && is_array($json)) {
        // OpenAI-ish
        if (isset($json["choices"][0]["message"]["content"])) {
          return trim((string) $json["choices"][0]["message"]["content"]);
        }
        // Other providers might return {text:"..."} or {output:"..."}
        if (isset($json["text"])) {
          return trim((string) $json["text"]);
        }
        if (isset($json["output"])) {
          return trim((string) $json["output"]);
        }
        return "ERROR: Unrecognized response structure" . $this->debug($json);
      } else {
        return "ERROR: HTTP " . $code . " - " . wp_remote_retrieve_response_message($res) . $this->debug(wp_remote_retrieve_body($res));
      }
      return "ERROR: Unrecognized response format";
    }
    public function debug($var) {
      return "yes" === $this->opts["debug"] ? "<hr><pre style='text-align: left; direction: ltr; border:1px solid gray; padding: 1rem; overflow: auto;'>" . print_r($var, 1) . "</pre>" : "";
    }
    /**
     * Simple local fallback generator (mock mode).
     *
     * @param array $ctx
     * @return string
     */
    private function mock_generate($ctx) {
      $name  = $ctx["customer"] ? $ctx["customer"] : __("Dear customer", "pd-ai-review-reply");
      $title = $ctx["product"]["title"];
      $base  = "Thanks for sharing your feedback about {$title}. We truly appreciate it!";
      if (stripos($ctx["review"], "bad") !== false || stripos($ctx["review"], "poor") !== false) {
        return "Hi {$name}, we're sorry to hear about your experience with {$title}. Please send your order number to our support so we can fix this right away.";
      }
      return "Hi {$name}! {$base} If you need any help or tips on using it, just let us know. Enjoy!";
    }

    /**
     * Sanitize and post-process model output.
     *
     * @param string $text
     * @param WC_Product|null $product
     * @return string
     */
    private function sanitize_reply($text, $product) {
      $text = trim(wp_strip_all_tags($text));
      $text = preg_replace("/\\s+/", " ", $text);
      // Avoid leaking sensitive info accidentally
      $blacklist = array("credit card", "password");
      foreach ($blacklist as $bad) {
        if (stripos($text, $bad) !== false) {
          $text = str_ireplace($bad, "****", $text);
        }
      }
      return $text;
    }
  }

  add_action("plugins_loaded", function () {
    global $PD_AI_Review_Reply;
    $PD_AI_Review_Reply = new PD_AI_Review_Reply;
  }, 2);
}
/*##################################################
Lead Developer: [amirhp-com](https://amirhp.com/)
##################################################*/