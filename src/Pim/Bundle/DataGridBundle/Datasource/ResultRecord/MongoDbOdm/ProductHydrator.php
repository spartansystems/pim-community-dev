<?php

namespace Pim\Bundle\DataGridBundle\Datasource\ResultRecord\MongoDbOdm;

use Doctrine\ODM\MongoDB\Query\Builder;
use Doctrine\ODM\MongoDB\Query\Query;
use Oro\Bundle\DataGridBundle\Datasource\ResultRecord;
use Pim\Bundle\DataGridBundle\Datasource\ResultRecord\HydratorInterface;
use Pim\Bundle\DataGridBundle\Datasource\ResultRecord\MongoDbOdm\Product\AssociationTransformer;
use Pim\Bundle\DataGridBundle\Datasource\ResultRecord\MongoDbOdm\Product\CompletenessTransformer;
use Pim\Bundle\DataGridBundle\Datasource\ResultRecord\MongoDbOdm\Product\FamilyTransformer;
use Pim\Bundle\DataGridBundle\Datasource\ResultRecord\MongoDbOdm\Product\FieldsTransformer;
use Pim\Bundle\DataGridBundle\Datasource\ResultRecord\MongoDbOdm\Product\GroupsTransformer;
use Pim\Bundle\DataGridBundle\Datasource\ResultRecord\MongoDbOdm\Product\ValuesTransformer;

/**
 * Hydrate results of Doctrine MongoDB query as ResultRecord array
 *
 * @author    Nicolas Dupont <nicolas@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductHydrator implements HydratorInterface
{
    /**
     * @param Builder $qb
     * {@inheritdoc}
     */
    public function hydrate($qb, array $options = [])
    {
        $locale  = $options['locale_code'];
        $scope   = $options['scope_code'];
        $config  = $options['attributes_configuration'];
        $groupId = $options['current_group_id'];
        $associationTypeId = $options['association_type_id'];
        $currentProduct    = $options['current_product'];

        /** @var Query $query */
        $query   = $qb->hydrate(false)->getQuery();
        $queryArray = $query->getQuery();

        $match = $queryArray['query'];

        $collection = $query->getDocumentManager()->getDocumentCollection('Pim\Bundle\CatalogBundle\Model\Product');

        $pipeline = array(
            array('$match' => $match),
            array('$project' =>
                array(
                    '_id' => 1,
                    'is_associated' => array(
                        '$cond' => array(
                            array('$eq' => array('$_id','5506d5bd8ead0e28408b46d0')),
                            1,
                            0
                        )
                    )
                )
            )
//            array('$unwind' => '$values'),
//            array(
//                '$group'  => array(
//                    '_id'       => array('id' => '$_id', 'family' => '$family'),
//                    'attribute' => array( '$addToSet' => '$values.attribute')
//                )
//            )
        );


        $foo = $collection->aggregate($pipeline)->toArray();

        $results = $query->execute();

        $attributes = [];
        foreach ($config as $attributeConf) {
            $attributes[$attributeConf['id']] = $attributeConf;
        }

        $rows = [];
        $fieldsTransformer = new FieldsTransformer();
        $valuesTransformer = new ValuesTransformer();
        $familyTransformer = new FamilyTransformer();
        $complTransformer  = new CompletenessTransformer();
        $groupsTransformer = new GroupsTransformer();
        $assocTramsformer  = new AssociationTransformer();

        foreach ($results as $result) {
            $result = $fieldsTransformer->transform($result, $locale);
            $result = $valuesTransformer->transform($result, $attributes, $locale, $scope);
            $result = $familyTransformer->transform($result, $locale);
            $result = $complTransformer->transform($result, $locale, $scope);
            $result = $groupsTransformer->transform($result, $locale, $groupId);
            $result = $assocTramsformer->transform($result, $associationTypeId, $currentProduct);

            $rows[] = new ResultRecord($result);
        }

        return $rows;
    }
}
