
<h1 align="center">Sylius Klarna Gateway Plugin</h1>

<p align="center">Headless checkout</p>
  
## Table of contents  
* [Plugin Development Documentation](#pddoc)  
* [Configuration](#r-config)  
  * [Environment variables](#r-config-env)  
  * [General configurations](#r-config-general)  
  * [State machine](#r-config-sm)  
  * [Routes](#r-config-routes)  
  * [Template](#r-config-template)  
* [Credentials encryption](#r-cred)
* [Klarna Checkout API](#r-api)
  * [Additional parameter explanation](#r-api-additional)
* [Quickstart Development](#r-quickstart)
* [Usage](#r-usage)
  * [Running plugin tests](#r-tests)
  * [Opening Sylius with your plugin](#r-tests-open)
  

<h2 id="r-pddoc">Plugin Development Documentation</h2>

Official Plugin guide: https://docs.sylius.com/en/latest/book/plugins/guide/index.html  
Following this tutorial for *custom payment gateways*: https://docs.sylius.com/en/1.12/cookbook/payments/custom-payment-gateway.html  
And this tutorial for *Klarna Checkout*: https://docs.klarna.com/klarna-checkout/get-started/  
Official API documentation: https://docs.klarna.com/api/checkout/  
Payum documentation for encrypting gateway configuration: https://github.com/Payum/Payum/blob/master/docs/symfony/encrypt-gateway-configs-stored-in-database.md  

<h2 id="r-config">Configuration</h2>

<h3 id="r-config-env">Environment variables</h3>
```ENV
PAYUM_CYPHER_KEY=OUTPUT_OF_vendor/bin/defuse-generate-key
```

<h3 id="r-config-general">General configurations</h3>
Add cypher-key configuration. This should point to the environment variable defined above.

Add the Klarna Checkout URI. This URI is dependent on region and if it is in development or in production.

The configuration will be loaded into the service container and will be accessible in a parameter bag.

This config is for development environment:

```yaml
north_creation_agency_sylius_klarna_gateway:
    cypher:
        key: '%env(PAYUM_CYPHER_KEY)%'
    checkout:
        headless: true
        silent_exception: false
        push_confirmation: https://api.playground.klarna.com/ordermanagement/v1/orders/{order_id}/acknowledge
        read_order: https://api.playground.klarna.com/ordermanagement/v1/orders/{order_id}
        uri: https://api.playground.klarna.com/checkout/v3/orders
    refund:
        include_shipping: false
```

`headless` is if redirects through Sylius application will be handled there, or you will make a separate complete request.
`silent_exception` is if an exception should  not be thrown if payment could not be verified on Klarna. When set to `true` a cart will still be created if payment has not been handled by Klarna.
`refund.include_shipping` defaults to false. This will refund the whole amount of a purchase except the shipping cost when set to false. When set to true, the shipping cost is included in the refund.

<h3 id="r-config-sm">State Machine</h3>  
Import state machine configuration for refund hook.
In config/packages/_sylius.yaml, import the configuration:
```yaml
imports:
    - { resource: "@NorthCreationAgencySyliusKlarnaGatewayPlugin/config/packages/state_machine.yaml" }
```

<h3 id="r-config-routes">Routes</h3>
Default Sylius Klarna routes are imported in your app's routes.yaml file:
```yaml 
app_klarna:
    resource: '@NorthCreationAgencySyliusKlarnaGatewayPlugin/config/klarna_routes.yaml'
```
This will add:   
* `klarna-checkout/push`: A POST endpoint for retrieving and confirming Klarna Push request

**API endpoints**  
Add the following to _./config/api_platform/Order.yaml_:
```yaml
App\Model\Order\Order:
    itemOperations:
        shop_klarna_widget:
            method: 'GET'
            path: 'shop/orders/{tokenValue}/klarna-widget'
            controller: NorthCreationAgency\SyliusKlarnaGatewayPlugin\Controller\KlarnaCheckoutController::getSnippet
            openapi_context:
                summary: Returns a Klarna checkout snippet
                parameters:
                    -   name: 'organizationRegistrationId'
                        in: 'query'
                        description: 'Optional Organization Registraion ID for B2B customer. Example: 556036-0793'
                        required: false
                        schema:
                            type: string
                    -   name: 'type'
                        in: 'query'
                        description: 'Select "organization" for b2b customers'
                        required: false
                        schema:
                            type: string
                            enum: ['person', 'organization']
        shop_klarna_confirmation_widget:
            method: 'GET'
            path: 'shop/orders/{tokenValue}/klarna-confirmation-widget'
            controller: NorthCreationAgency\SyliusKlarnaGatewayPlugin\Controller\KlarnaCheckoutController::getConfirmationSnippet
            openapi_context:
                summary: Returns a Klarna checkout confirmation snippet
                responses:
                    200:
                        description: 'OK'
                        content:
                            application/json:
                                schema:
                                    type: object
                                    properties:
                                        snippet:
                                            type: string
                                            description: 'The Klarna checkout confirmation snippet'
                                            example: '<div id="klarna-checkout-container"></div>'
        shop_klarna_confirmation:
            method: 'GET'
            path: 'shop/orders/{tokenValue}/klarna-confirmation'
            controller: NorthCreationAgency\SyliusKlarnaGatewayPlugin\Controller\KlarnaCheckoutController::confirmHeadless
            openapi_context:
                summary: Confirms current payment status of a Klarna order
                responses:
                    200:
                        description: 'OK'
                        content:
                            application/json:
                                schema:
                                    type: object
                                    properties:
                                        message:
                                            type: string
                                            description: 'Updated payment status'
                                            example: 'Updated payment state. New state: paid'
                    400:
                        description: 'BAD REQUEST'
                        content:
                            application/json:
                                schema:
                                    type: object
                                    properties:
                                        request_status:
                                            type: integer
                                            description: 'Klarna server status'
                                        error_message:
                                            type: string
                                            description: 'Error details'
                    500:
                        description: 'INTERNAL SERVER ERROR'
                        content:
                            application/json:
                                schema:
                                    type: object
                                    properties:
                                        error_message:
                                            type: string
                                            description: 'Error details'
```

In _plugin development_ environment, the api-config is imported by adding the following to 
api_platform.mapping.paths in tests/Application/config/packages/api_platform.yaml:
```yaml
- '%kernel.project_dir%/../../sylius-klarna-gateway-plugin/config/api_platform'
```

In a _project using the plugin_, the api-config is imported by adding the following to
api_platform.mapping.paths in tests/Application/config/packages/api_platform.yaml:
```yaml
- '%kernel.project_dir%/vendor/andersbjorkland/sylius-klarna-gateway-plugin/config/api_platform'
```

<h3 id="r-config-template">Template</h3>
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

<h2 id="r-cred">Credentials encryption</h2>
Makes use of `defuse/php-encryption` to encrypt the credentials. 
This in turn requires `ext-openssl` to be installed on the server.

<h2 id="r-api">Klarna Checkout API</h2>
The test-url for the api is accessed via: https://beeceptor.com/console/klarna

<h3 id="r-api-additional">Additional parameter explanation</h3>
*When not covered by official documentation*  
In general, all `amount`-parameters are in cents or equivalent. `2000`: EUR 20.00  
`tax-rate` is a percentage value. `25`: 25% = 25/100 = 0.25  
`order-amount` is the total amount of the order, including tax.

<h2 id="r-quickstart">Quickstart Development</h2>

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

<h2 id="r-usage">Usage</h2>

<h3 id="r-tests">Running plugin tests</h3>

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

<h3 id="r-tests-open">Opening Sylius with your plugin<h3>

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
