<?php

function ga_script() {
    ?>  
        <!-- Global site tag (gtag.js) - Google Analytics -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-S1CLFKG6YC"></script>
        <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', 'G-S1CLFKG6YC');
        </script>
    <?php
}
add_action('wp_head', 'ga_script');

