<?php

/**
 * @method HpsPayPlanScheduleServiceAddBuilder withData(array $data)
 */
class HpsPayPlanScheduleServiceAddBuilder extends HpsBuilderAbstract
{
    /** @var array */
    protected $data = array();

    /**
     * Instatiates a new HpsPayPlanScheduleServiceAddBuilder
     *
     * @param HpsRestGatewayService $service
     */
    public function __construct(HpsRestGatewayService $service)
    {
        parent::__construct($service);
        $this->setUpValidations();
    }

    /**
     * Creates an add transaction through the HpsPayPlanScheduleService
     */
    public function execute()
    {
        parent::execute();

        $service = new HpsPayPlanScheduleService($this->service->servicesConfig());
        $obj = new HpsPayPlanSchedule();

        foreach ($this->data as $k => $v) {
            $obj->$k = $v;
        }
        unset($usableData, $k, $v);

        return $service->add($obj);
    }

    /**
     * Setups up validations for building payment schedule
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
     * @return HpsPayPlanScheduleServiceAddBuilder
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;
        return $this;
    }
}
