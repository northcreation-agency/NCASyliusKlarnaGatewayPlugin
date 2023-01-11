
<h1 align="center">Sylius Klarna Gateway Plugin</h1>

<p align="center">Headless checkout</p>

## Documentation

Official Plugin guide: https://docs.sylius.com/en/latest/book/plugins/guide/index.html  
Following this tutorial for *custom payment gateways*: https://docs.sylius.com/en/1.12/cookbook/payments/custom-payment-gateway.html  
And this tutorial for *Klarna Checkout*: https://docs.klarna.com/klarna-checkout/get-started/  
Official API documentation: https://docs.klarna.com/api/checkout/  
Payum documentation for encrypting gateway configuration: https://github.com/Payum/Payum/blob/master/docs/symfony/encrypt-gateway-configs-stored-in-database.md  

## Credentials encryption
Makes use of `defuse/php-encryption` to encrypt the credentials. 
This in turn requires `ext-openssl` to be installed on the server.

## Klarna Checkout API  
The test-url for the api is accessed via: https://beeceptor.com/console/klarna  

### Recommended ENV variables
```ENV
PAYUM_CYPHER_KEY=OUTPUT_OF_vendor/bin/defuse-generate-key
KLARNA_SECRET=test_secret_key
KLARNA_CHECKOUT_URI=https://klarna.free.beeceptor.com/checkout
KLARNA_USERNAME=test_username
KLARNA_PASSWORD=test_password
```

### Additional parameter explanation
*When not covered by official documentation*  
In general, all `amount`-parameters are in cents or equivalent. `2000`: EUR 20.00  
`tax-rate` is a percentage value. `25`: 25% = 25/100 = 0.25  
`order-amount` is the total amount of the order, including tax.

## Quickstart Development

1. Git clone project.

2. From the project root directory, run the following commands:

    ```bash
    $ docker-compose up -d
    $ composer install
    $ (cd tests/Application && yarn install)
    $ (cd tests/Application && yarn build)
    $ (cd tests/Application && APP_ENV=test bin/console assets:install public)
    
    $ (cd tests/Application && APP_ENV=test bin/console doctrine:database:create)
    $ (cd tests/Application && APP_ENV=test bin/console doctrine:schema:create)
    $ (cd tests/Application && APP_ENV=test bin/console sylius:fixtures:load)
    $ (cd tests/Application && APP_ENV=test symfony serve -d)
    ```
   > Note: If you do not have Symfony CLI installed, you can use the php built-in server instead:  
   > `php -S localhost:8000 -t public`

## Usage

### Running plugin tests

  - PHPUnit

    ```bash
    vendor/bin/phpunit
    ```

  - PHPSpec

    ```bash
    vendor/bin/phpspec run
    ```

  - Behat (non-JS scenarios)

    ```bash
    vendor/bin/behat --strict --tags="~@javascript"
    ```

  - Behat (JS scenarios)
 
    1. [Install Symfony CLI command](https://symfony.com/download).
 
    2. Start Headless Chrome:
    
         ```bash
         google-chrome-stable --enable-automation --disable-background-networking --no-default-browser-check --no-first-run --disable-popup-blocking --disable-default-apps --allow-insecure-localhost --disable-translate --disable-extensions --no-sandbox --enable-features=Metal --headless --remote-debugging-port=9222 --window-size=2880,1800 --proxy-server='direct://' --proxy-bypass-list='*' http://127.0.0.1
         ```
    
    3. Install SSL certificates (only once needed) and run test application's webserver on `127.0.0.1:8080`:
    
         ```bash
         symfony server:ca:install
         APP_ENV=test symfony server:start --port=8080 --dir=tests/Application/public --daemon
         ```
    
    4. Run Behat:
    
         ```bash
         vendor/bin/behat --strict --tags="@javascript"
         ```
    
  - Static Analysis
  
    - Psalm
    
      ```bash
      vendor/bin/psalm
      ```
      
    - PHPStan
    
      ```bash
      vendor/bin/phpstan analyse -c phpstan.neon -l max src/  
      ```

  - Coding Standard
  
    ```bash
    vendor/bin/ecs check src
    ```

### Opening Sylius with your plugin

- Using `test` environment:

    ```bash
    (cd tests/Application && APP_ENV=test bin/console sylius:fixtures:load)
    (cd tests/Application && APP_ENV=test bin/console server:run -d public)
    ```
    
- Using `dev` environment:

    ```bash
    (cd tests/Application && APP_ENV=dev bin/console sylius:fixtures:load)
    (cd tests/Application && APP_ENV=dev bin/console server:run -d public)
    ```
