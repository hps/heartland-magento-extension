<?php

/**
 * @method HpsPayPlanCustomerServiceEditBuilder withData(array $data)
 * @method HpsPayPlanCustomerServiceEditBuilder withCustomer(HpsPaymentCustomer $customer)
 * @method HpsPayPlanCustomerServiceEditBuilder withCustomerKey(string $customerKey)
 */
class HpsPayPlanCustomerServiceEditBuilder extends HpsBuilderAbstract
{
    /** @var array */
    protected $data        = array();

    /** @var HpsPaymentCustomer|null */
    protected $customer    = null;

    /** @var string|null */
    protected $customerKey = null;

    /**
     * Instatiates a new HpsPayPlanCustomerServiceEditBuilder
     *
     * @param HpsRestGatewayService $service
     */
    public function __construct(HpsRestGatewayService $service)
    {
        parent::__construct($service);
        $this->setUpValidations();
    }

    /**
     * Creates an edit transaction through the HpsPayPlanCustomerService
     */
    public function execute()
    {
        parent::execute();

        if ($this->customer != null) {
            $this->customerKey = $this->customer->customerKey;
        }

        $service = new HpsPayPlanCustomerService($this->service->servicesConfig());
        $obj = new HpsPayPlanCustomer();
        $obj->customerKey = $this->customerKey;

        $usableData = array_intersect_key(
            $this->data,
            array_flip(HpsPayPlanCustomer::getEditableFields())
        );
        foreach ($usableData as $k => $v) {
            $obj->$k = $v;
        }
        unset($usableData, $k, $v);

        return $service->edit($obj);
    }

    /**
     * Setups up validations for building customer
     * edits.
     *
     * @return null
     */
    private function setUpValidations()
    {
        $this
            ->addValidation(array($this, 'onlyOneCustomerIdentifier'), 'HpsArgumentException', 'Edit can only use one customer identifier ($customer or $customerKey)')
            ->addValidation(array($this, 'dataNotEmpty'), 'HpsArgumentException', 'Edit needs a non-empty data set');
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

    /**
     * Ensures the data set is not empty.
     *
     * @param array $actionCounts
     *
     * @return bool
     */
    protected function dataNotEmpty($actionCounts)
    {
        return !empty($this->data);
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return HpsPayPlanCustomerServiceEditBuilder
     */
    public function update($key, $value)
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * @param string $key
     *
     * @return HpsPayPlanCustomerServiceEditBuilder
     */
    public function ignore($key)
    {
        return $this->update($key, null);
    }
}
