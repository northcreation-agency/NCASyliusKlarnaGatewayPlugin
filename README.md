
<h1 align="center">Sylius Klarna Gateway Plugin</h1>

<p align="center">Headless checkout</p>

## Documentation

Official Plugin guide: https://docs.sylius.com/en/latest/book/plugins/guide/index.html  
Following this tutorial for *custom payment gateways*: https://docs.sylius.com/en/1.12/cookbook/payments/custom-payment-gateway.html  
And this tutorial for *Klarna Checkout*: https://docs.klarna.com/klarna-checkout/get-started/  
Official API documentation: https://docs.klarna.com/api/checkout/  
Payum documentation for encrypting gateway configuration: https://github.com/Payum/Payum/blob/master/docs/symfony/encrypt-gateway-configs-stored-in-database.md  

## Configuration

### Environment variables
```ENV
PAYUM_CYPHER_KEY=OUTPUT_OF_vendor/bin/defuse-generate-key
```

### General configurations
Add cypher-key configuration. This should point to the environment variable defined above.

Add the Klarna Checkout URI. This URI is dependent on region and if it is in development or in production.

The configuration will be loaded into the service container and will be accessible in a parameter bag.

This config is for development environment:

```yaml
north_creation_agency_sylius_klarna_gateway:
    cypher:
        key: '%env(PAYUM_CYPHER_KEY)%'
    checkout:
        uri: https://api.playground.klarna.com/checkout/v3/orders
```

### Routes
Default Sylius Klarna routes are imported in your app's routes.yaml file:
```yaml 
app_klarna:
    resource: '@NorthCreationAgencySyliusKlarnaGatewayPlugin/config/klarna_routes.yaml'
```
This will add:   
* `klarna-checkout/push`: A POST endpoint for retrieving and confirming Klarna Push request

**API endpoints**  
In _plugin development_ environment, the api-config is imported by adding the following to 
api_platform.mapping.paths in tests/Application/config/packages/api_platform.yaml:
```yaml
- '%kernel.project_dir%/../../sylius-klarna-gateway-plugin/config/api_platform'
```

In a _project using the plugin_, the api-config is imported by adding the following to
api_platform.mapping.paths in tests/Application/config/packages/api_platform.yaml:
```yaml
- '%kernel.project_dir%/../../vendor/andersbjorkland/sylius-klarna-gateway-plugin/config/api_platform'
```

### Template
The Klarna Widget is used in an iframe. This boilerplate contains required JavaScript code and 
where to paste the widget:  
```html 
<html>

<head>
</head>

<body>

    <textarea style="display: none" id="KCO">
        <!-- PASTE the widget snippet here! -->
    </textarea>

    <div id="klarna-checkout-wrapper">
    </div>

    <!-- START - Dont edit -->
    <script type="text/javascript">
        var checkoutContainer = document.getElementById('klarna-checkout-wrapper')
        checkoutContainer.innerHTML = (document.getElementById("KCO").value).replace(/\\"/g, "\"").replace(/\\n/g, "");
        var scriptsTags = checkoutContainer.getElementsByTagName('script')
        for (var i = 0; i < scriptsTags.length; i++) {
            var parentNode = scriptsTags[i].parentNode
            var newScriptTag = document.createElement('script')
            newScriptTag.type = 'text/javascript'
            newScriptTag.text = scriptsTags[i].text
            parentNode.removeChild(scriptsTags[i])
            parentNode.appendChild(newScriptTag)
        }
    </script>
    <!-- END -->

</body>
</html>

```

After the purchase has been handled by Klarna and redirected to the checkout page, you may now confirm that the purchase 
has been correctly handled, you may retrieve the order-status to `/checkout/v3/orders/{klarna-order-id}`, which will 
contain the confirmation snippet. Paste this is a similar fashion as the one before.

## Credentials encryption
Makes use of `defuse/php-encryption` to encrypt the credentials. 
This in turn requires `ext-openssl` to be installed on the server.

## Klarna Checkout API  
The test-url for the api is accessed via: https://beeceptor.com/console/klarna

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
