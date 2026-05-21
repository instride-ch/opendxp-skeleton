# Custom Nginx Configuration

Place custom Nginx `.conf` files in this directory to have them deployed to `/etc/nginx/conf.d/custom-user.d/` on the server.

## Environment targeting

Suffix the filename with the environment name to restrict it to a specific environment:

| Filename              | Deployed to      |
| --------------------- | ---------------- |
| `my-rule.conf`        | all environments |
| `my-rule-stg.conf`    | `stg` only       |
| `my-rule-prd.conf`    | `prd` only       |

Files without an environment suffix are uploaded to every environment.

## Deployment

During deployment, `deploy:nginx:config` uploads the matching files, then `deploy:nginx:reload` runs `nginx -t && sudo service nginx reload` to apply them. Any nginx syntax error will abort the reload.