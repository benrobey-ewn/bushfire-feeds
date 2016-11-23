#!/bin/bash
#This file needs to be ran in the root directory of bushifire-traffic.
php -f index_http.php
php -f api_bushfires.php
php -f api_traffic.php
