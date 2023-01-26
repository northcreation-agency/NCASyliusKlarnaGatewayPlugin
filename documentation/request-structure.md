The request structure that is KlarnaRequestStructure is based on the API documentation found at
https://docs.klarna.com/api/checkout/#operation/createOrderMerchant

TODO: support `billing_countries`  
Currently it does not support the key `billing_countries`. Providing countries to this key in the request will 
open it up to changing billing country inside the Klarna Widget iFrame. This in turn will require Sylius to add a 
route for Klarna to update the billing country if the user changes it. If we do not provide billing countries, 
the user will only have the chosen billing country from earlier in the checkout process.

The `AllowedCountriesRetriever::getCountryCodes(Channel $channel)` can be used to get an array of country codes when 
this feature is supported.
