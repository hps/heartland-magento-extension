<?php

/**
 * @method HpsPayPlanScheduleServiceEditBuilder withData(array $data)
 * @method HpsPayPlanScheduleServiceEditBuilder withSchedule(HpsPayPlanSchedule $paymentSchedule)
 * @method HpsPayPlanScheduleServiceEditBuilder withScheduleKey(string $paymentScheduleKey)
 */
class HpsPayPlanScheduleServiceEditBuilder extends HpsBuilderAbstract
{
    /** @var array */
    protected $data               = array();

    /** @var HpsPayPlanSchedule|null */
    protected $paymentSchedule    = null;

    /** @var string|null */
    protected $paymentScheduleKey = null;

    /**
     * Instatiates a new HpsPayPlanScheduleServiceEditBuilder
     *
     * @param HpsRestGatewayService $service
     */
    public function __construct(HpsRestGatewayService $service)
    {
        parent::__construct($service);
        $this->setUpValidations();
    }

    /**
     * Creates an edit transaction through the HpsPayPlanScheduleService
     */
    public function execute()
    {
        parent::execute();

        if ($this->paymentSchedule != null) {
            $this->paymentScheduleKey = $this->paymentSchedule->paymentScheduleKey;
        }

        $service = new HpsPayPlanScheduleService($this->service->servicesConfig());
        $obj = new HpsPayPlanSchedule();
        $obj->paymentScheduleKey = $this->paymentScheduleKey;

        $usableData = array_intersect_key(
            $this->data,
            array_flip(HpsPayPlanSchedule::getEditableFields())
        );
        foreach ($usableData as $k => $v) {
            $obj->$k = $v;
        }
        unset($usableData, $k, $v);

        return $service->edit($obj);
    }

    /**
     * Setups up validations for building payment schedule
     * edits.
     *
     * @return null
     */
    private function setUpValidations()
    {
        $this
            ->addValidation(array($this, 'onlyOneScheduleIdentifier'), 'HpsArgumentException', 'Edit can only use one payment schedule identifier ($paymentSchedule or $paymentScheduleKey)')
            ->addValidation(array($this, 'dataNotEmpty'), 'HpsArgumentException', 'Edit needs a non-empty data set');
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
     * @return HpsPayPlanScheduleServiceEditBuilder
     */
    public function update($key, $value)
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * @param string $key
     *
     * @return HpsPayPlanScheduleServiceEditBuilder
     */
    public function ignore($key)
    {
        return $this->update($key, null);
    }
}
