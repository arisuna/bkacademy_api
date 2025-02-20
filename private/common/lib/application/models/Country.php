<?php

namespace SMXD\Application\Models;

class Country extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var string
     * @Column(column="name", type="string", length=64, nullable=true)
     */
    protected $name;

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(column="id", type="integer", length=11, nullable=false)
     */
    protected $id;

    /**
     *
     * @var string
     * @Column(column="label", type="string", length=255, nullable=true)
     */
    protected $label;

    /**
     *
     * @var string
     * @Column(column="label_seo", type="string", length=128, nullable=true)
     */
    protected $label_seo;

    /**
     *
     * @var string
     * @Column(column="cio", type="string", length=2, nullable=false)
     */
    protected $cio;

    /**
     *
     * @var string
     * @Column(column="slug", type="string", length=255, nullable=true)
     */
    protected $slug;

    /**
     *
     * @var string
     * @Column(column="cio_flag", type="string", length=3, nullable=true)
     */
    protected $cio_flag;

    /**
     *
     * @var integer
     * @Column(column="top", type="integer", length=1, nullable=false)
     */
    protected $top;

    /**
     *
     * @var integer
     * @Column(column="secondary", type="integer", length=1, nullable=false)
     */
    protected $secondary;

    /**
     *
     * @var integer
     * @Column(column="active", type="integer", length=1, nullable=false)
     */
    protected $active;

    /**
     *
     * @var string
     * @Column(column="currency_name", type="string", length=50, nullable=true)
     */
    protected $currency_name;

    /**
     *
     * @var string
     * @Column(column="currency_iso", type="string", length=5, nullable=true)
     */
    protected $currency_iso;

    /**
     *
     * @var string
     * @Column(column="iso3", type="string", length=10, nullable=true)
     */
    protected $iso3;

    /**
     *
     * @var string
     * @Column(column="lang", type="string", length=10, nullable=true)
     */
    protected $lang;

    /**
     *
     * @var string
     * @Column(column="continent", type="string", length=2, nullable=true)
     */
    protected $continent;

    /**
     *
     * @var string
     * @Column(column="capital", type="string", length=128, nullable=true)
     */
    protected $capital;

    /**
     *
     * @var string
     * @Column(column="phone", type="string", length=10, nullable=true)
     */
    protected $phone;

    /**
     *
     * @var string
     * @Column(column="neighbours", type="string", length=100, nullable=true)
     */
    protected $neighbours;

    /**
     *
     * @var integer
     * @Column(column="geonameid", type="integer", length=11, nullable=true)
     */
    protected $geonameid;

    /**
     *
     * @var integer
     * @Column(column="iso_numeric", type="integer", length=11, nullable=true)
     */
    protected $iso_numeric;

    /**
     *
     * @var integer
     * @Column(column="population", type="integer", length=11, nullable=true)
     */
    protected $population;

    /**
     *
     * @var string
     * @Column(column="alternative_names", type="string", length=255, nullable=true)
     */
    protected $alternative_names;

    /**
     * Method to set the value of field name
     *
     * @param string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Method to set the value of field id
     *
     * @param integer $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Method to set the value of field label
     *
     * @param string $label
     * @return $this
     */
    public function setLabel($label)
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Method to set the value of field label_seo
     *
     * @param string $label_seo
     * @return $this
     */
    public function setLabelSeo($label_seo)
    {
        $this->label_seo = $label_seo;

        return $this;
    }

    /**
     * Method to set the value of field cio
     *
     * @param string $cio
     * @return $this
     */
    public function setCio($cio)
    {
        $this->cio = $cio;

        return $this;
    }

    /**
     * Method to set the value of field slug
     *
     * @param string $slug
     * @return $this
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Method to set the value of field cio_flag
     *
     * @param string $cio_flag
     * @return $this
     */
    public function setCioFlag($cio_flag)
    {
        $this->cio_flag = $cio_flag;

        return $this;
    }

    /**
     * Method to set the value of field top
     *
     * @param integer $top
     * @return $this
     */
    public function setTop($top)
    {
        $this->top = $top;

        return $this;
    }

    /**
     * Method to set the value of field secondary
     *
     * @param integer $secondary
     * @return $this
     */
    public function setSecondary($secondary)
    {
        $this->secondary = $secondary;

        return $this;
    }

    /**
     * Method to set the value of field active
     *
     * @param integer $active
     * @return $this
     */
    public function setActive($active)
    {
        $this->active = $active;

        return $this;
    }

    /**
     * Method to set the value of field currency_name
     *
     * @param string $currency_name
     * @return $this
     */
    public function setCurrencyName($currency_name)
    {
        $this->currency_name = $currency_name;

        return $this;
    }

    /**
     * Method to set the value of field currency_iso
     *
     * @param string $currency_iso
     * @return $this
     */
    public function setCurrencyIso($currency_iso)
    {
        $this->currency_iso = $currency_iso;

        return $this;
    }

    /**
     * Method to set the value of field iso3
     *
     * @param string $iso3
     * @return $this
     */
    public function setIso3($iso3)
    {
        $this->iso3 = $iso3;

        return $this;
    }

    /**
     * Method to set the value of field lang
     *
     * @param string $lang
     * @return $this
     */
    public function setLang($lang)
    {
        $this->lang = $lang;

        return $this;
    }

    /**
     * Method to set the value of field continent
     *
     * @param string $continent
     * @return $this
     */
    public function setContinent($continent)
    {
        $this->continent = $continent;

        return $this;
    }

    /**
     * Method to set the value of field capital
     *
     * @param string $capital
     * @return $this
     */
    public function setCapital($capital)
    {
        $this->capital = $capital;

        return $this;
    }

    /**
     * Method to set the value of field phone
     *
     * @param string $phone
     * @return $this
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Method to set the value of field neighbours
     *
     * @param string $neighbours
     * @return $this
     */
    public function setNeighbours($neighbours)
    {
        $this->neighbours = $neighbours;

        return $this;
    }

    /**
     * Method to set the value of field geonameid
     *
     * @param integer $geonameid
     * @return $this
     */
    public function setGeonameid($geonameid)
    {
        $this->geonameid = $geonameid;

        return $this;
    }

    /**
     * Method to set the value of field iso_numeric
     *
     * @param integer $iso_numeric
     * @return $this
     */
    public function setIsoNumeric($iso_numeric)
    {
        $this->iso_numeric = $iso_numeric;

        return $this;
    }

    /**
     * Method to set the value of field population
     *
     * @param integer $population
     * @return $this
     */
    public function setPopulation($population)
    {
        $this->population = $population;

        return $this;
    }

    /**
     * Method to set the value of field alternative_names
     *
     * @param string $alternative_names
     * @return $this
     */
    public function setAlternativeNames($alternative_names)
    {
        $this->alternative_names = $alternative_names;

        return $this;
    }

    /**
     * Returns the value of field name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the value of field id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Returns the value of field label
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * Returns the value of field label_seo
     *
     * @return string
     */
    public function getLabelSeo()
    {
        return $this->label_seo;
    }

    /**
     * Returns the value of field cio
     *
     * @return string
     */
    public function getCio()
    {
        return $this->cio;
    }

    /**
     * Returns the value of field slug
     *
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * Returns the value of field cio_flag
     *
     * @return string
     */
    public function getCioFlag()
    {
        return $this->cio_flag;
    }

    /**
     * Returns the value of field top
     *
     * @return integer
     */
    public function getTop()
    {
        return $this->top;
    }

    /**
     * Returns the value of field secondary
     *
     * @return integer
     */
    public function getSecondary()
    {
        return $this->secondary;
    }

    /**
     * Returns the value of field active
     *
     * @return integer
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Returns the value of field currency_name
     *
     * @return string
     */
    public function getCurrencyName()
    {
        return $this->currency_name;
    }

    /**
     * Returns the value of field currency_iso
     *
     * @return string
     */
    public function getCurrencyIso()
    {
        return $this->currency_iso;
    }

    /**
     * Returns the value of field iso3
     *
     * @return string
     */
    public function getIso3()
    {
        return $this->iso3;
    }

    /**
     * Returns the value of field lang
     *
     * @return string
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * Returns the value of field continent
     *
     * @return string
     */
    public function getContinent()
    {
        return $this->continent;
    }

    /**
     * Returns the value of field capital
     *
     * @return string
     */
    public function getCapital()
    {
        return $this->capital;
    }

    /**
     * Returns the value of field phone
     *
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Returns the value of field neighbours
     *
     * @return string
     */
    public function getNeighbours()
    {
        return $this->neighbours;
    }

    /**
     * Returns the value of field geonameid
     *
     * @return integer
     */
    public function getGeonameid()
    {
        return $this->geonameid;
    }

    /**
     * Returns the value of field iso_numeric
     *
     * @return integer
     */
    public function getIsoNumeric()
    {
        return $this->iso_numeric;
    }

    /**
     * Returns the value of field population
     *
     * @return integer
     */
    public function getPopulation()
    {
        return $this->population;
    }

    /**
     * Returns the value of field alternative_names
     *
     * @return string
     */
    public function getAlternativeNames()
    {
        return $this->alternative_names;
    }

    /**
     * Method to set the value of field flag_prefix
     *
     * @param string $flag_prefix
     * @return $this
     */
    public function setFlagPrefix($flag_prefix)
    {
        $this->flag_prefix = $flag_prefix;

        return $this;
    }

    /**
     * Method to set the value of field currency
     *
     * @param string $currency
     * @return $this
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Returns the value of field flag_prefix
     *
     * @return string
     */
    public function getFlagPrefix()
    {
        return $this->flag_prefix;
    }

    /**
     * Returns the value of field currency
     *
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->hasMany('id', 'SMXD\Application\Models\Geoname', 'country_id', array('alias' => 'Geoname'));
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Country[]
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Country
     */
    public static function findFirst($parameters = null): \Phalcon\Mvc\ModelInterface
    {
        return parent::findFirst($parameters);
    }

    /**
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'country';
    }

}
