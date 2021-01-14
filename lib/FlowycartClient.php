<?php


namespace Flowycart;


use ErrorException;
use Curl\Curl;
use InvalidArgumentException;

class FlowycartClient
{
    /** @var string default base url for Flowycart's API */
    const DEFAULT_API_BASE = 'https://api.flowycart.com/api';

    /** @var array<string, mixed> */
    private $config;

    /** @var array<string, mixed> */
    private $defaultConfig = [
        'api_key' => null,
        'client_id' => null,
        'api_base' => self::DEFAULT_API_BASE,
    ];

    /**
     * Initializes a new instance of the {@link FlowycartClient} class.
     *
     * The constructor takes a single argument. The argument can be a string, in which case it
     * should be the API key. It can also be an array with various configuration settings.
     *
     * Configuration settings include the following options:
     *
     * - api_key (null|string): the Flowycart API key, to be used in regular API requests.
     * - client_id (null|string): the Flowycart client ID, to be used in OAuth requests.
     *
     * The following configuration settings are also available, though setting these should rarely be necessary
     * (only useful if you want to send requests to a mock server):
     *
     * - api_base (string): the base URL for regular API requests. Defaults to
     *   {@link DEFAULT_API_BASE}.
     *
     * @param array<string, mixed>|string $config the API key as a string, or an array containing
     *   the client configuration settings
     *
     * @throws ErrorException
     */
    public function __construct($config = [])
    {
        if (is_string($config)) {
            $config = ['api_key' => $config];
        } elseif (!is_array($config)) {
            throw new InvalidArgumentException('$config must be a string or an array');
        }

        if (!extension_loaded('curl')) {
            throw new ErrorException('cURL library is not loaded');
        }

        $config = array_merge($this->defaultConfig, $config);

        $this->validateConfig($config);

        $this->config = $config;
    }

    /**
     * Gets the API key used by the client to send requests.
     *
     * @return null|string the API key used by the client to send requests
     */
    public function getApiKey()
    {
        return $this->config['api_key'];
    }

    /**
     * Gets the base URL for Flowycart's API.
     *
     * @return string the base URL for Flowycart's API
     */
    public function getApiBase()
    {
        return $this->config['api_base'];
    }

    /**
     * Sends a createOrder request to Flowycart's API
     *
     * @param array $params OrderInputType params. It must be a key-value array in correspondence with the OrderInputType
     * fields:
     *
     *<code>
     * - refId (null|string): vendor order id
     * - intent (boolean): flag that indicates if the order is an order that is about to pass through the checkout
     * process or not
     * - items (array): items or products that conform the order. Each item (ItemInputType) could have the following
     * values:
     *      - name (string): item name.
     *      - description (null|string): item description
     *      - images (null|string): array containing the list of the images of the product
     *      - amount (null|float): item price
     *      - quantity (null|int): item quantity
     *      - variant (null|array): variants or options of the item. Each variant (ItemVariantInputType) must have the
     *      following values:
     *          - key (string): variant key
     *          - value (null|string): variant value
     *      - taxRate (null|float): tax quantity to apply to the item price depending on the value of the field: taxType
     *      - taxType (null|string): tax type to apply to the product. The valid values are: "PERCENT" and "PRICE". If
     *      you select "PERCENT" the taxRate value will be taken as the percent tax to apply to the item cost.
     *      Otherwise, if you select "PRICE" the taxRate value will be taken as a fixed amount to apply as tax.
     * - currency (string): currency code in which the payment will be made.
     * - currencyValue (null|float): value of the currency. This value has as reference the e-comerce default currency
     * - successUrl (string): url to redirect the user when the payment process is completed.
     * - cancelUrl (string): url to redirect the user when the payment process is canceled.
     * - customerId (null|string): Flowycart customer id who made the order.
     * - metadata (null|array): key-value array with metadata of the order. Each metadata item (OrderMetadataInputType)
     * must have the following values:
     *      - key (string): variant key
     *      - value (null|string): variant value
     *</code>
     *
     * @param null|array $extraReturnFields the fields the user wants to get from the created order
     *
     * @return array the created customer data (CustomerType) selected fields. By default the field
     * id will be returned. E.g <code>['id' => <id>, ...]</code>
     *
     * @throws ErrorException
     */
    public function createOrder($params, $extraReturnFields = []){
        $defaultReturnFields = ['id'];
        $returnFields = join(' ', array_merge($defaultReturnFields, $extraReturnFields));

        $mutation = "
             mutation createOrder(\$orderInput: OrderInputType!){
               createOrder(order: \$orderInput){
                 id
                 status
                 order{
                    {$returnFields}
                 }
               }
             }        
        ";

        $variables = ['orderInput' => $params];

        $responseData = $this->graphQLRequest($mutation, $variables);

        if ($responseData->createOrder->id === null)
            throw new ErrorException($responseData->createOrder->status);

        return (array) $responseData->createOrder->order;
    }

    /**
     *
     * Sends a createCustomer request to Flowycart's API
     *
     * @param array $params CustomerInputType params. It must be a key-value array in correspondence with the
     * CustomerInputType fields:
     *
     * <code>
     * - refId (string): vendor customer id
     * - firstName (null|string): customer first name
     * - lastName (null|string): customer lastName
     * - email (string): customer email
     * - addresses (null|array): customer addresses. Each item (AddressInputType) in the array can have the following values:
     *      - id (null|string): address id, if not set a new address will be created, otherwise the address with the
     *      specified id will be updated
     *      - firstName (null|string): firstname of the person who belongs the address
     *      - lastname (null|string): lastname of the person who belongs the address
     *      - line1 (string): address line 1
     *      - line2 (null|string): address line 2
     *      - zip (string): address zip code
     *      - phone (null|string): phone number
     *      - country (null|array): address country. A key-value array representing the type CountryInputType
     *      containing the following fields:
     *          - id (string): id of the country in Flowycart.
     *      - zone (null|array): address zone. A key-value array representing the type ZoneInputType containing the
     *      following fields:
     *          - id (string): id of the zone in Flowycart.
     *      - city (null|string): address city.
     * </code>
     *
     * @param null|array $extraReturnFields the extra fields the user wants to get from the created customer
     *
     * @return array the created customer data (CustomerType) selected fields. By default the field
     * id will be returned. E.g <code>['id' => <id>, ...]</code>
     *
     * @throws ErrorException
     */
    public function createCustomer($params, $extraReturnFields = []){
        $defaultReturnFields = ['id'];
        $returnFields = join(' ', array_merge($defaultReturnFields, $extraReturnFields));

        $mutation = "
            mutation createCustomer(\$customerInput: CustomerInputType!){
              createCustomer(customer: \$customerInput){
                customer{
                  {$returnFields}
                }
                status
              }
            }
        ";

        $variables = ['customerInput' => $params];

        $responseData = $this->graphQLRequest($mutation, $variables);

        if ($responseData->createCustomer->customer === null)
            throw new ErrorException($responseData->createCustomer->status);

        return (array) $responseData->createCustomer->customer;
    }


    /**
     *
     * Sends a connectMerchant request to Flowycart's API. This endpoint will return a token that will be useb by the
     * e-comerce to validate the callback requests from Flowycart.
     *
     * @param string $baseUrl the base url of the callbacks endpoints on the e-comerce side
     * @param string $vendor the name of the e-comerce. E.g. Opencart, Magento, etc.
     *
     * @return array an array containing the generated token: ['token' => '<tokenStr>']
     *
     * @throws ErrorException
     */
    public function connectMerchant($baseUrl, $vendor){
        $mutation = '
            mutation connectMerchant($baseUrl:String!, $vendor:String!){
              connectMerchant(baseUrl:$baseUrl, vendor:$vendor){
                status
                token
              }
            }
        ';

        $variables = compact('baseUrl', 'vendor');
        $responseData = $this->graphQLRequest($mutation, $variables);

        if ($responseData->connectMerchant->token == null)
            throw new ErrorException($responseData->connectMerchant->status);

        return ['token' => $responseData->connectMerchant->token];
    }


    /**
     * Obtains all countries data stored in Flowycart platform
     *
     * @param null|array $extraReturnFields the extra fields the user wants to get from each country
     *
     * @return array the countries data (CountryType). By default each country will have the following fields:
     * id, name, codeIso2, codeIso3
     *
     * @throws ErrorException
     */
    public function getCountries($extraReturnFields = []){
        $defaultReturnFields = ['id', 'name', 'codeIso2', 'codeIso3'];
        $returnFields = join(' ', array_merge($defaultReturnFields, $extraReturnFields));

        $query = "
            query countries{
              countries{
              {$returnFields}
              }
            }
        ";

        return $this->graphQLRequest($query)->countries;
    }


    /**
     * @param string $countryId the Flowycart country id you want to get the zones from
     *
     * @param null|array $extraReturnFields the extra fields the user wants to get from each zone.

     * @return array the zones data (CountryType). By default each zone will have the following fields:
     * id, name, code
     *
     * @throws ErrorException
     */
    public function getZones($countryId, $extraReturnFields = []){
        $defaultReturnFields = ['id', 'name', 'code'];
        $returnFields = join(' ', array_merge($defaultReturnFields, $extraReturnFields));

        $query = "
            query zones(\$countryId: String!){
              zones(countryId: \$countryId){
                {$returnFields}
              }
            }
        ";

        $variables = compact('countryId');

        return $this->graphQLRequest($query, $variables)->zones;
    }

    /**
     * Sends a GraphQL request to Flowycart's API
     * @param string $query the GraphQL query string
     * @param array $variables the GraphQL query variables (optional)
     * @return mixed json object
     * @throws ErrorException
     */
    public function graphQLRequest($query, $variables = []){

        if ($query === null || $query === '')
            throw new InvalidArgumentException('$query cannot be null or an empty string');

        if (!is_array($variables))
            throw new InvalidArgumentException('$variables must be an array');

        $curl = new Curl($this->getApiBase() . '/graphql/');

        $curl->setHeader('Authorization', $this->getApiKey());
        $curl->setHeader('Accept-Encoding', 'gzip');
        $curl->setHeader('Content-Type', 'application/json');

        $curl->post(compact('query', 'variables'));

        if ($curl->httpStatusCode < 200 || $curl->httpStatusCode >= 300)
            $this->handleHTTPErrorResponse($curl);

        // Handling GraphQL errors, following GraphQL specs (http://spec.graphql.org/June2018/#sec-Response-Format).
        // Here we are assuming that if there is an error the data is invalid. Another option would be assuming that if
        // there is data then there is no error.
        if (isset($curl->response->errors))
            $this->handleGraphQLErrors($curl->response->errors);

        return $curl->response->data;
    }

    /**
     * @param array<object> $errors
     * @throws ErrorException
     */
    private function handleGraphQLErrors(array $errors){
        $message = array_shift($errors)->message;
        foreach ($errors as $error){
            $message .= "\n{$error->message}";
        }
        throw new ErrorException($message);
    }

    /**
     * @param Curl $curl
     * @throws ErrorException
     */
    private function handleHTTPErrorResponse($curl){
        if (!$curl->error){
            $msg = "Invalid response from API: {$curl->response} "
                . "(HTTP response code was {$curl->httpStatusCode})";

            throw new ErrorException($msg);
        }

        throw new ErrorException($curl->errorMessage, $curl->errorCode);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws InvalidArgumentException
     */
    private function validateConfig($config)
    {
        // api_key
        if (null === $config['api_key']){
            throw new InvalidArgumentException('api_key cannot be null');
        }

        if (!is_string($config['api_key'])) {
            throw new InvalidArgumentException('api_key must a string');
        }

        if ($config['api_key'] === '') {
            throw new InvalidArgumentException('api_key cannot be the empty string');
        }

        if (preg_match('/\s/', $config['api_key'])) {
            throw new InvalidArgumentException('api_key cannot contain whitespace');
        }

        // client_id
        if ($config['client_id'] !== null && !is_string($config['client_id'])) {
            throw new InvalidArgumentException('client_id must be null or a string');
        }

        // api_base
        if (!is_string($config['api_base'])) {
            throw new InvalidArgumentException('api_base must be a string');
        }

        // check absence of extra keys
        $extraConfigKeys = array_diff(array_keys($config), array_keys($this->defaultConfig));
        if (!empty($extraConfigKeys)) {
            throw new InvalidArgumentException('Found unknown key(s) in configuration array: ' . implode(',', $extraConfigKeys));
        }
    }
}