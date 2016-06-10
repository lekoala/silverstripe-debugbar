<?php

class DebugBarSilverStripeCollector extends DebugBar\DataCollector\DataCollector implements DebugBar\DataCollector\Renderable
{

    public function collect()
    {
        return array("env" => Director::get_environment_type());
    }

    public function getName()
    {
        return 'silverstripe';
    }

    public function getWidgets()
    {
        return array(
            "silverstripe" => array(
                "icon" => "server",
                "tooltip" => "Environment",
                "map" => "silverstripe.env",
                "default" => "''"
            )
        );
    }
}