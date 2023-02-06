#!/bin/bash

SED=`which gsed || which sed`

$SED -i'' 's#"name": "pantheon-upstreams/drupal-composer-managed"#"name": "pantheon-upstreams/decoupled-drupal-composer-managed"#g' composer.json

# Because the precise spacing and formatting is unknown, new lines
# need to preserve the previous formatting, so here we duplicate the
# core-recommended line since the new lines need to come afterward,
# while prepending PTARGET1 and PTARGET2 in order to provide find/replace
# targets for the following step.
$SED -i'' -n 'p;s/"drupal\/core-recommended":/PTARGET1&/p' composer.json
$SED -i'' -n 'p;s/PTARGET1/PTARGET2&/p' composer.json

# Having duplicated the previous lines, we do a standard replace operation.
$SED -i'' 's#PTARGET2.*#"drupal/pantheon_decoupled_umami_demo": "^1.0",#g' composer.json
$SED -i'' 's#PTARGET1.*#"drupal/pantheon_decoupled_profile": "^1.0",#g' composer.json

$SED -i'' 's#"minimum-stability": "stable"#"minimum-stability": "alpha"#g' composer.json
