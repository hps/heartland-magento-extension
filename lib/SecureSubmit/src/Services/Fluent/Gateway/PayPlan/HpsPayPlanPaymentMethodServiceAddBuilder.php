<?php

/**
 * @method HpsPayPlanPaymentMethodServiceAddBuilder withData(array $data)
 */
class HpsPayPlanPaymentMethodServiceAddBuilder extends HpsBuilderAbstract
{
    /** @var array */
    protected $data = array();

    /**
     * Instatiates a new HpsPayPlanPaymentMethodServiceAddBuilder
     *
     * @param HpsRestGatewayService $service
     */
    public function __construct(HpsRestGatewayService $service)
    {
        parent::__construct($service);
        $this->setUpValidations();
    }

    /**
     * Creates an add transaction through the HpsPayPlanPaymentMethodService
     */
    public function execute()
    {
        parent::execute();

        $service = new HpsPayPlanPaymentMethodService($this->service->servicesConfig());
        $obj = new HpsPayPlanPaymentMethod();

        foreach ($this->data as $k => $v) {
            $obj->$k = $v;
        }
        unset($usableData, $k, $v);

        return $service->add($obj);
    }

    /**
     * Setups up validations for building payment method
     * adds.
     *
     * @return null
     */
    private function setUpValidations()
    {
        $this
            ->addValidation(array($this, 'dataNotEmpty'), 'HpsArgumentException', 'Add needs a non-empty data set');
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
     * @return HpsPayPlanPaymentMethodServiceAddBuilder
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;
        return $this;
    }
}
