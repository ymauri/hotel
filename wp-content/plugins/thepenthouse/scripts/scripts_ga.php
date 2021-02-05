<?php

function ga_script() {
    ?>  
        <script async src="https://www.googletagmanager.com/gtag/js?id=UA-261093413-1"></script>
        <script>window.dataLayer = window.dataLayer || [];function gtag(){dataLayer.push(arguments);}gtag('js', new Date());gtag('config', 'UA-261093413-1');</script>
    <?php
}
add_action('wp_head', 'ga_script');

