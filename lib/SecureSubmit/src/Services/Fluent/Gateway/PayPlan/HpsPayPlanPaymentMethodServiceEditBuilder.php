<?php

/**
 * @method HpsPayPlanPaymentMethodServiceEditBuilder withData(array $data)
 * @method HpsPayPlanPaymentMethodServiceEditBuilder withPaymentMethod(HpsPayPlanPaymentMethod $paymentMethod)
 * @method HpsPayPlanPaymentMethodServiceEditBuilder withPaymentMethodKey(string $paymentMethodKey)
 */
class HpsPayPlanPaymentMethodServiceEditBuilder extends HpsBuilderAbstract
{
    /** @var array */
    protected $data             = array();

    /** @var HpsPayPlanPaymentMethod|null */
    protected $paymentMethod    = null;

    /** @var string|null */
    protected $paymentMethodKey = null;

    /**
     * Instatiates a new HpsPayPlanPaymentMethodServiceEditBuilder
     *
     * @param HpsRestGatewayService $service
     */
    public function __construct(HpsRestGatewayService $service)
    {
        parent::__construct($service);
        $this->setUpValidations();
    }

    /**
     * Creates an edit transaction through the HpsPayPlanPaymentMethodService
     */
    public function execute()
    {
        parent::execute();

        if ($this->paymentMethod != null) {
            $this->paymentMethodKey = $this->paymentMethod->paymentMethodKey;
        }

        $service = new HpsPayPlanPaymentMethodService($this->service->servicesConfig());
        $obj = new HpsPayPlanPaymentMethod();
        $obj->paymentMethodKey = $this->paymentMethodKey;

        $usableData = array_intersect_key(
            $this->data,
            array_flip(HpsPayPlanPaymentMethod::getEditableFields())
        );
        foreach ($usableData as $k => $v) {
            $obj->$k = $v;
        }
        unset($usableData, $k, $v);

        return $service->edit($obj);
    }

    /**
     * Setups up validations for building payment method
     * edits.
     *
     * @return null
     */
    private function setUpValidations()
    {
        $this
            ->addValidation(array($this, 'onlyOnePaymentMethodIdentifier'), 'HpsArgumentException', 'Edit can only use one payment method identifier ($paymentMethod or $paymentMethodKey)')
            ->addValidation(array($this, 'dataNotEmpty'), 'HpsArgumentException', 'Edit needs a non-empty data set');
    }

    /**
     * Ensures there is only one payment method identifier, and
     * checks that there is only one paymentMethod or one
     * paymentMethodKey in use. Both cannot be used.
     *
     * @param array $actionCounts
     *
     * @return bool
     */
    public function onlyOnePaymentMethodIdentifier($actionCounts)
    {
        return (isset($actionCounts['paymentMethod']) && $actionCounts['paymentMethod'] == 1
                && (!isset($actionCounts['paymentMethodKey'])
                    || isset($actionCounts['paymentMethodKey']) && $actionCounts['paymentMethodKey'] == 0))
            || (isset($actionCounts['paymentMethodKey']) && $actionCounts['paymentMethodKey'] == 1
                && (!isset($actionCounts['paymentMethod'])
                    || isset($actionCounts['paymentMethod']) && $actionCounts['paymentMethod'] == 0));
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
     * @return HpsPayPlanPaymentMethodServiceEditBuilder
     */
    public function update($key, $value)
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * @param string $key
     *
     * @return HpsPayPlanPaymentMethodServiceEditBuilder
     */
    public function ignore($key)
    {
        return $this->update($key, null);
    }
}
