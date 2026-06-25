<?php

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'contentshake_settings' ) ) {
        wp_die( __( 'Security check failed', 'contentshake' ) );
    }
}

$domain       = home_url();
$current_user = wp_get_current_user();
$user_email   = $current_user->user_email;
$tokens       = get_option( CONTENTSHAKE_API_KEY_OPTION, array() );
$user_token   = '';
$connected    = false;

if ( array_key_exists( $user_email, $tokens ) ) {
    $connected = true;
} else {
    $acception_tokens = get_option( CONTENTSHAKE_API_KEY_ACCEPTING_OPTION, array() );
    if ( ! array_key_exists( $user_email, $acception_tokens ) ) {
        $user_token = bin2hex( openssl_random_pseudo_bytes( 16 ) );
        $tokens[$user_email] = $user_token;
        update_option( CONTENTSHAKE_API_KEY_ACCEPTING_OPTION, $tokens );
    } else {
        $user_token = $acception_tokens[$user_email];
        $tokens[$user_email] = $user_token;
    }
}

if ( isset($_POST['disconnect']) && isset($_POST['token']) ) {
    if ( array_key_exists( $user_email, $tokens ) ) {
        unset($tokens[$user_email]);
        update_option(CONTENTSHAKE_API_KEY_OPTION, $tokens);
    }
    $user_token = '';
    $connected  = false;
}

$redirect_url = sprintf('%s?domain=%s&user=%s&token=%s', CONTENTSHAKE_AUTH_URL, $domain, $user_email, $user_token);

?>

<style>

body {
    line-height: 4.0em;
}

body {
    width: 100%;
}

#wpwrap {
    background: linear-gradient(180deg, #DCEEEB 0%, #E8E1FF 75%, #FFF 100%);
}

.cs-settings img {
    pointer-events: none;
}

.cs-settings__padding {
    padding: 40pt;
    padding-top: 60pt;
    padding-bottom: 10pt;
}

.cs-settings__row {
    display: flex;
    width: 100%;
}

.cs-settings__title {
    margin-top: 25pt;
    font-size: 40px;
    font-family: Verdana, Geneva, Tahoma, sans-serif;
    font-style: normal;
    font-weight: 700;
    line-height: 117%;
    color: #000000;
}

.cs-settings__title--accent {
    margin-top: 0;
}

.cs-settings__text {
    margin-top: 25pt;
    font-size: 12pt;
    font-family: Verdana, Geneva, Tahoma, sans-serif;
    font-style: normal;
    color: #000000;
}

.cs-settings__link {
    color: #000000;
    text-decoration: underline;
}

.cs-settings__btn {
    margin-top: 25pt;
    padding: 16px 28px;
    border-radius: 6px;
    border-width: 0px;
    color: #FFFFFF;
    background-color: #A261FD;
    font-family: Verdana, Geneva, Tahoma, sans-serif;
    font-style: normal;
}

.cs-settings__btn:hover {
    background-color: #8A40E3;
}

.cs-settings__btn:active {
    background-color: #6C31C9;
}

.cs-settings__banner-frame {
    display: inline-block;
    position: relative;
    margin-left: 60pt;
    padding: 20px;
    border: 1px solid #FFFFFF;
    border-radius: 8px;
    overflow: hidden;
    background-size: cover;
    background-position: center;
}

.cs-settings__banner-frame::before {
    content: "";
    position: absolute;
    inset: 0;
    background-image: inherit;
    background-size: cover;
    background-position: center;
}

.cs-settings__banner {
    display: block;
    position: relative;
    border-radius: 4px;
}

</style>

<div class="cs-settings cs-settings__padding">
    <div class="cs-settings__row">
        <div class="cs-settings__col">

            <img alt="Content Toolkit" src="<?php echo esc_attr(plugin_dir_url(__DIR__) . '/images/logo.png'); ?>" srcset="<?php echo esc_attr(plugin_dir_url(__DIR__) . '/images/logo@2x.png'); ?> 2x, <?php echo esc_attr(plugin_dir_url(__DIR__) . '/images/logo@3x.png'); ?> 3x" />
            <div class="cs-settings__title"><?php _e( 'Expand your brand with', 'contentshake' ); ?></div>
            <div class="cs-settings__title cs-settings__title--accent">Content Toolkit</div>

            <?php if ( $connected ): ?>
                <div class="cs-settings__text"><?php _e( 'Connected to Content Toolkit', 'contentshake' ); ?></div>
            <?php else: ?>
                <div class="cs-settings__text"><?php _e( 'Don\'t have an account yet?', 'contentshake' ); ?> <a href="https://www.semrush.com/signup/" class="cs-settings__link"><?php _e( 'Sign up', 'contentshake' ); ?></a></div>
            <?php endif; ?>

            <form action="" method="post">
                <?php wp_nonce_field('contentshake_settings'); ?>
                <?php if ( ! $connected ) : ?>
                    <input type="hidden" name="connect" value="true">
                    <input type="submit" name="submit" id="submit" class="cs-settings__btn" value="<?php _e( 'Connect Content Toolkit', 'contentshake' ); ?>">
                <?php else: ?>
                    <input type="hidden" name="token" id="semrush_contentshake_api_key" value="<?php echo esc_attr($user_token); ?>">
                    <input type="hidden" name="disconnect" value="true">
                    <input type="submit" name="submit" id="submit" class="cs-settings__btn" value="<?php echo esc_attr(__('Disconnect Content Toolkit', 'contentshake')); ?>">
                <?php endif; ?>
            </form>

        </div>
        <div class="cs-settings__col">

            <div class="cs-settings__banner-frame">
                <img class="cs-settings__banner" src="<?php echo esc_attr(plugin_dir_url(__DIR__) . '/images/banner.png'); ?>" srcset="<?php echo esc_attr(plugin_dir_url(__DIR__) . '/images/banner@2x.png'); ?> 2x, <?php echo esc_attr(plugin_dir_url(__DIR__) . '/images/banner@3x.png'); ?> 3x" />
            </div>

        </div>
    </div>
</div>

<?php if (isset($_POST['connect'])) : ?>
    <div class="cs-settings__padding">
        <span class="cs-settings__text"><?php _e('Redirecting to', 'contentshake'); ?> <a href="<?php echo esc_attr($redirect_url); ?>" class="cs-settings__link">Semrush</a>...</span>
    </div>
    <script>
        setTimeout(function() {
            window.location = '<?php echo esc_attr(CONTENTSHAKE_AUTH_URL) ?>?domain=<?php echo esc_attr($domain) ?>&user=<?php echo esc_attr($user_email) ?>&token=<?php echo esc_attr($user_token) ?>';
        }, 2000);
    </script>
<?php endif; ?>
