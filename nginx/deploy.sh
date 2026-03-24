#!/bin/bash

# Deploy to the Plesk Server
scp bot-blocking.conf bot-blocking-wp-login.conf squarecandy@cl.sqcdy.com:/etc/nginx/firewall/
ssh squarecandy@cl.sqcdy.com "sudo nginx -t && sudo plesk bin service --restart nginx"
