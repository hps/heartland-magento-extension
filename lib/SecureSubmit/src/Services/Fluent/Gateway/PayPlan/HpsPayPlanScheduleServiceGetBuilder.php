<?php

/**
 * @method HpsPayPlanScheduleServiceGetBuilder withData(array $data)
 * @method HpsPayPlanScheduleServiceGetBuilder withSchedule(HpsPayPlanSchedule $paymentSchedule)
 * @method HpsPayPlanScheduleServiceGetBuilder withScheduleKey(string $paymentScheduleKey)
 */
class HpsPayPlanScheduleServiceGetBuilder extends HpsBuilderAbstract
{
    /** @var array */
    protected $data               = array();

    /** @var HpsPayPlanSchedule|null */
    protected $paymentSchedule    = null;

    /** @var string|null */
    protected $paymentScheduleKey = null;

    /**
     * Instatiates a new HpsPayPlanScheduleServiceGetBuilder
     *
     * @param HpsRestGatewayService $service
     */
    public function __construct(HpsRestGatewayService $service)
    {
        parent::__construct($service);
        $this->setUpValidations();
    }

    /**
     * Creates an get transaction through the HpsPayPlanScheduleService
     */
    public function execute()
    {
        parent::execute();

        if ($this->paymentSchedule != null) {
            $this->paymentScheduleKey = $this->paymentSchedule->paymentScheduleKey;
        }

        $service = new HpsPayPlanScheduleService($this->service->servicesConfig());

        return $service->get($this->paymentScheduleKey);
    }

    /**
     * Setups up validations for building payment schedule
     * gets.
     *
     * @return null
     */
    private function setUpValidations()
    {
        $this
            ->addValidation(array($this, 'onlyOneScheduleIdentifier'), 'HpsArgumentException', 'Get can only use one payment schedule identifier ($paymentSchedule or $paymentScheduleKey)');
    }

    /**
     * Ensures there is only one payment schedule identifier, and
     * checks that there is only one paymentSchedule or one
     * paymentScheduleKey in use. Both cannot be used.
     *
     * @param array $actionCounts
     *
     * @return bool
     */
    public function onlyOneScheduleIdentifier($actionCounts)
    {
        return (isset($actionCounts['paymentSchedule']) && $actionCounts['paymentSchedule'] == 1
                && (!isset($actionCounts['paymentScheduleKey'])
                    || isset($actionCounts['paymentScheduleKey']) && $actionCounts['paymentScheduleKey'] == 0))
            || (isset($actionCounts['paymentScheduleKey']) && $actionCounts['paymentScheduleKey'] == 1
                && (!isset($actionCounts['paymentSchedule'])
                    || isset($actionCounts['paymentSchedule']) && $actionCounts['paymentSchedule'] == 0));
    }
}
