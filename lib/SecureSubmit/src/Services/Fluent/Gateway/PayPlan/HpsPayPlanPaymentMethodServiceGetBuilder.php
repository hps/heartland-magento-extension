<?php

/**
 * @method HpsPayPlanPaymentMethodServiceGetBuilder withData(array $data)
 * @method HpsPayPlanPaymentMethodServiceGetBuilder withPaymentMethod(HpsPayPlanPaymentMethod $paymentMethod)
 * @method HpsPayPlanPaymentMethodServiceGetBuilder withPaymentMethodKey(string $paymentMethodKey)
 */
class HpsPayPlanPaymentMethodServiceGetBuilder extends HpsBuilderAbstract
{
    /** @var array */
    protected $data               = array();

    /** @var HpsPayPlanPaymentMethod|null */
    protected $paymentMethod    = null;

    /** @var string|null */
    protected $paymentMethodKey = null;

    /**
     * Instatiates a new HpsPayPlanPaymentMethodServiceGetBuilder
     *
     * @param HpsRestGatewayService $service
     */
    public function __construct(HpsRestGatewayService $service)
    {
        parent::__construct($service);
        $this->setUpValidations();
    }

    /**
     * Creates an get transaction through the HpsPayPlanPaymentMethodService
     */
    public function execute()
    {
        parent::execute();

        if ($this->paymentMethod != null) {
            $this->paymentMethodKey = $this->paymentMethod->paymentMethodKey;
        }

        $service = new HpsPayPlanPaymentMethodService($this->service->servicesConfig());

        return $service->get($this->paymentMethodKey);
    }

    /**
     * Setups up validations for building payment method
     * gets.
     *
     * @return null
     */
    private function setUpValidations()
    {
        $this
            ->addValidation(array($this, 'onlyOnePaymentMethodIdentifier'), 'HpsArgumentException', 'Get can only use one payment method identifier ($paymentMethod or $paymentMethodKey)');
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
}
