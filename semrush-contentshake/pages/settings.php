<?php

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
    background: #008275;
    line-height: 4.0em;
}

img {
  pointer-events: none;
}

.padding {
    padding: 40pt; 
    padding-top: 60pt; 
    padding-bottom: 10pt;
}

.btn {
    margin-top: 25pt;  
    padding: 16px 28px; 
    border-radius: 6px;
    border-width: 0px; 
    color: #191B23; 
    background-color: #FFFFFF; 
    font-family: Verdana, Geneva, Tahoma, sans-serif; 
    font-style: normal;
}

.btn:hover {
    background-color: #DDDDDD;
}

.btn:active {
    background-color: #AAAAAA;
}

.banner {
    display: inline;
    margin-left: 100pt;
}

@media (max-width: 840px) { 
    .padding {
        padding: 0pt; 
        padding-top: 60pt;
    }
    
    .banner {
        display: inline;
        margin: 40pt 0pt;
    }
}

</style>

<div class="padding">
    <div style="width: 100%; overflow:auto;">
        <div style="float:left">
    
            <img alt="ContentShake" src="<?php echo esc_attr(plugin_dir_url(__DIR__) . '/images/logo.png'); ?>" srcset="<?php echo esc_attr(plugin_dir_url(__DIR__) . '/images/logo@2x.png'); ?> 2x, <?php echo esc_attr(plugin_dir_url(__DIR__) . '/images/logo@3x.png'); ?> 3x" />
            <div style="color: #FFFFFF; margin-top: 25pt; font-size: 38pt; font-family: Verdana, Geneva, Tahoma, sans-serif; font-style: normal;"><?php _e( 'Expand your brand with', 'contentshake' ); ?></div>
            <div style="color: #FFE84D; font-size: 38pt; font-family: Verdana, Geneva, Tahoma, sans-serif; font-style: normal;">ContentShake</div>

            <?php if ( $connected ): ?>
                <div style="color: #FFFFFF; margin-top: 25pt; font-size: 12pt; font-family: Verdana, Geneva, Tahoma, sans-serif; font-style: normal;"><?php _e( 'Connected to ContentShake', 'contentshake' ); ?></div>
            <?php else: ?>
                <div style="color: #FFFFFF; margin-top: 25pt; font-size: 12pt; font-family: Verdana, Geneva, Tahoma, sans-serif; font-style: normal;"><?php _e( 'Don\'t have an account yet?', 'contentshake' ); ?> <a href="https://www.semrush.com/signup/" style="color: #FFFFFF; text-decoration: underline;"><?php _e( 'Sign up', 'contentshake' ); ?></a></div>
            <?php endif; ?>

            <form action="" method="post">
                <?php if ( ! $connected ) : ?>
                    <input type="hidden" name="connect" value="true">
                    <input type="submit" name="submit" id="submit" class="btn" value="<?php _e( 'Connect ContentShake', 'contentshake' ); ?>">
                <?php else: ?>
                    <input type="hidden" name="token" id="semrush_contentshake_api_key" value="<?php echo esc_attr($user_token); ?>">
                    <input type="hidden" name="disconnect" value="true">
                    <input type="submit" name="submit" id="submit" class="btn" value="<?php echo esc_attr(__('Disconnect ContentShake', 'contentshake')); ?>">
                <?php endif; ?>
            </form>

        </div>
        <div style="float:left;">
            
            <img class="banner" src="<?php echo esc_attr(plugin_dir_url(__DIR__) . '/images/banner.png'); ?>" srcset="<?php echo esc_attr(plugin_dir_url(__DIR__) . '/images/banner@2x.png'); ?> 2x, <?php echo esc_attr(plugin_dir_url(__DIR__) . '/images/banner@3x.png'); ?> 3x" />

        </div>
    </div>
</div>

<?php if (isset($_POST['connect'])) : ?>
    <div class="padding">
        <span style="color: #FFFFFF; font-size: 12pt; font-family: Verdana, Geneva, Tahoma, sans-serif; font-style: normal;"><?php _e('Redirecting to', 'contentshake'); ?> <a href="<?php echo esc_attr($redirect_url); ?>" style="color: #FFFFFF; text-decoration: underline;">Semrush</a>...</span>
    </div>
    <script>
        setTimeout(function() {
            window.location = '<?php echo esc_attr(CONTENTSHAKE_AUTH_URL) ?>?domain=<?php echo esc_attr($domain) ?>&user=<?php echo esc_attr($user_email) ?>&token=<?php echo esc_attr($user_token) ?>';
        }, 2000);
    </script>
<?php endif; ?>
