<?php

/**
 * @method HpsPayPlanScheduleServiceDeleteBuilder withData(array $data)
 * @method HpsPayPlanScheduleServiceDeleteBuilder withSchedule(HpsPayPlanSchedule $paymentSchedule)
 * @method HpsPayPlanScheduleServiceDeleteBuilder withScheduleKey(string $paymentScheduleKey)
 */
class HpsPayPlanScheduleServiceDeleteBuilder extends HpsBuilderAbstract
{
    /** @var array */
    protected $data               = array();

    /** @var HpsPayPlanSchedule|null */
    protected $paymentSchedule    = null;

    /** @var string|null */
    protected $paymentScheduleKey = null;

    /**
     * Instatiates a new HpsPayPlanScheduleServiceDeleteBuilder
     *
     * @param HpsRestGatewayService $service
     */
    public function __construct(HpsRestGatewayService $service)
    {
        parent::__construct($service);
        $this->setUpValidations();
    }

    /**
     * Creates an delete transaction through the HpsPayPlanScheduleService
     */
    public function execute()
    {
        parent::execute();

        if ($this->paymentSchedule != null) {
            $this->paymentScheduleKey = $this->paymentSchedule->paymentScheduleKey;
        }

        $service = new HpsPayPlanScheduleService($this->service->servicesConfig());

        return $service->delete($this->paymentScheduleKey);
    }

    /**
     * Setups up validations for building payment schedule
     * deletes.
     *
     * @return null
     */
    private function setUpValidations()
    {
        $this
            ->addValidation(array($this, 'onlyOneScheduleIdentifier'), 'HpsArgumentException', 'Delete can only use one payment schedule identifier ($paymentSchedule or $paymentScheduleKey)');
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
