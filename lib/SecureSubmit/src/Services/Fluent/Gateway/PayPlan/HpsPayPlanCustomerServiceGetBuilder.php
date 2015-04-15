<?php

/**
 * @method HpsPayPlanCustomerServiceGetBuilder withData(array $data)
 * @method HpsPayPlanCustomerServiceGetBuilder withCustomer(HpsPayPlanCustomer $customer)
 * @method HpsPayPlanCustomerServiceGetBuilder withCustomerKey(string $customerKey)
 */
class HpsPayPlanCustomerServiceGetBuilder extends HpsBuilderAbstract
{
    /** @var array */
    protected $data               = array();

    /** @var HpsPayPlanCustomer|null */
    protected $customer    = null;

    /** @var string|null */
    protected $customerKey = null;

    /**
     * Instatiates a new HpsPayPlanCustomerServiceGetBuilder
     *
     * @param HpsRestGatewayService $service
     */
    public function __construct(HpsRestGatewayService $service)
    {
        parent::__construct($service);
        $this->setUpValidations();
    }

    /**
     * Creates an get transaction through the HpsPayPlanCustomerService
     */
    public function execute()
    {
        parent::execute();

        if ($this->customer != null) {
            $this->customerKey = $this->customer->customerKey;
        }

        $service = new HpsPayPlanCustomerService($this->service->servicesConfig());

        return $service->get($this->customerKey);
    }

    /**
     * Setups up validations for building customer
     * gets.
     *
     * @return null
     */
    private function setUpValidations()
    {
        $this
            ->addValidation(array($this, 'onlyOneCustomerIdentifier'), 'HpsArgumentException', 'Get can only use one customer identifier ($customer or $customerKey)');
    }

    /**
     * Ensures there is only one customer identifier, and
     * checks that there is only one customer or one
     * customerKey in use. Both cannot be used.
     *
     * @param array $actionCounts
     *
     * @return bool
     */
    public function onlyOneCustomerIdentifier($actionCounts)
    {
        return (isset($actionCounts['customer']) && $actionCounts['customer'] == 1
                && (!isset($actionCounts['customerKey'])
                    || isset($actionCounts['customerKey']) && $actionCounts['customerKey'] == 0))
            || (isset($actionCounts['customerKey']) && $actionCounts['customerKey'] == 1
                && (!isset($actionCounts['customer'])
                    || isset($actionCounts['customer']) && $actionCounts['customer'] == 0));
    }
}
