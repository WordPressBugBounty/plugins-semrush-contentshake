<?php

$current_user = wp_get_current_user();
$user_email   = $current_user->user_email;
$tokens       = get_option( CONTENTSHAKE_API_KEY_OPTION, array() );

if ( array_key_exists( $user_email, $tokens ) ) {
    $user_token = $tokens[$user_email];
} else {
    $accepting = get_option( CONTENTSHAKE_API_KEY_ACCEPTING_OPTION, array() );
    if ( ! array_key_exists( $user_email, $accepting ) ) {
        $user_token = bin2hex( openssl_random_pseudo_bytes( 16 ) );
        $accepting[$user_email] = $user_token;
        update_option( CONTENTSHAKE_API_KEY_ACCEPTING_OPTION, $accepting );
    } else {
        $user_token = $accepting[$user_email];
    }
}

?>

<style>

body {
    width: 100%;
    line-height: 1.5em;
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
    margin-top: 24px;
    font-size: 40px;
    font-family: Verdana, Geneva, Tahoma, sans-serif;
    font-style: normal;
    font-weight: 700;
    line-height: 117%;
    color: #000000;
}

.cs-settings__text {
    margin-top: 30px;
    font-size: 16px;
    font-family: Verdana, Geneva, Tahoma, sans-serif;
    font-style: normal;
    color: #000000;
}

.cs-settings__token {
    margin-top: 30px;
    display: flex;
    gap: 12px;
}

.cs-settings .cs-settings__input {
    padding: 8px 12px;
    border-radius: 6px;
    font-family: monospace;
    font-size: 16px;
}

.cs-settings__btn {
    padding: 0 28px;
    border-radius: 6px;
    border-width: 0px;
    color: #FFFFFF;
    background-color: #A261FD;
    font-family: Verdana, Geneva, Tahoma, sans-serif;
    font-size: 16px;
    line-height: 1;
    cursor: pointer;
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
            <div class="cs-settings__title">
                <?php _e( 'Expand your brand with', 'contentshake' ); ?>
                <br>
                Content Toolkit
            </div>
            <div class="cs-settings__text"><label for="<?php echo esc_attr(CONTENTSHAKE_TOKEN_INPUT_ID); ?>"><?php /* translators: %s: link to semrush.com */ printf( __( 'Copy this token and paste into connection widget on %s.', 'contentshake' ), '<a href="https://www.semrush.com/content" target="_blank" rel="noopener">semrush.com</a>' ); ?></label></div>
            <div class="cs-settings__token">
                <input type="text" id="<?php echo esc_attr(CONTENTSHAKE_TOKEN_INPUT_ID); ?>" class="cs-settings__input" size="<?php echo esc_attr(strlen($user_token)); ?>" value="<?php echo esc_attr($user_token); ?>" readonly onclick="this.select()">
                <button type="button" id="<?php echo esc_attr(CONTENTSHAKE_COPY_BUTTON_ID); ?>" class="cs-settings__btn" data-copied="<?php echo esc_attr(__('Copied', 'contentshake')); ?>" data-failed="<?php echo esc_attr(__('Press Ctrl/Cmd+C', 'contentshake')); ?>"><?php _e( 'Copy', 'contentshake' ); ?></button>
            </div>
            <div class="cs-settings__text">
                <?php /* translators: %s: link to KB article */ printf( __( '%s about using this plugin.', 'contentshake' ), '<a href="https://www.semrush.com/kb/1565-how-to-use-the-content-toolkit-plugin-on-wordpress" target="_blank" rel="noopener">' . __( 'Learn more', 'contentshake' ) . '</a>' ); ?>
            </div>
        </div>
        <div class="cs-settings__col">

            <div class="cs-settings__banner-frame">
                <img class="cs-settings__banner" src="<?php echo esc_attr(plugin_dir_url(__DIR__) . '/images/banner.png'); ?>" srcset="<?php echo esc_attr(plugin_dir_url(__DIR__) . '/images/banner@2x.png'); ?> 2x, <?php echo esc_attr(plugin_dir_url(__DIR__) . '/images/banner@3x.png'); ?> 3x" />
            </div>

        </div>
    </div>
</div>

<script>
    (function () {
        var input  = document.getElementById('<?php echo esc_js(CONTENTSHAKE_TOKEN_INPUT_ID); ?>');
        var button = document.getElementById('<?php echo esc_js(CONTENTSHAKE_COPY_BUTTON_ID); ?>');
        var label  = button.textContent;

        // On failure the token stays selected, so the user can copy it by hand.
        function flash(copied) {
            button.textContent = copied ? button.dataset.copied : button.dataset.failed;
            setTimeout(function () {
                button.textContent = label;
            }, 2000);
        }

        // Copies the selected token; also the fallback when the Clipboard API refuses
        // (permissions, iframe policy).
        function copySelection() {
            flash(document.execCommand('copy'));
        }

        button.addEventListener('click', function () {
            input.focus();
            input.select();

            if (navigator.clipboard) {
                navigator.clipboard.writeText(input.value).then(
                    function () { flash(true); },
                    copySelection
                );
            } else {
                copySelection();
            }
        });
    })();
</script>
