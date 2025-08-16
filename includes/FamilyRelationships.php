<?php
// Shared FamilyRelationships utilities for relationship mapping
if (!class_exists('FamilyRelationships')) {
    class FamilyRelationships {
        public static $relationships = [
            'parent' => [
                'father' => 'Father',
                'mother' => 'Mother',
                'step-father' => 'Step-Father',
                'step-mother' => 'Step-Mother',
                'adoptive-father' => 'Adoptive Father',
                'adoptive-mother' => 'Adoptive Mother'
            ],
            'child' => [
                'son' => 'Son',
                'daughter' => 'Daughter',
                'step-son' => 'Step-Son',
                'step-daughter' => 'Step-Daughter',
                'adoptive-son' => 'Adoptive Son',
                'adoptive-daughter' => 'Adoptive Daughter'
            ],
            'grandparent' => [
                'grandfather' => 'Grandfather',
                'grandmother' => 'Grandmother',
                'step-grandfather' => 'Step-Grandfather',
                'step-grandmother' => 'Step-Grandmother'
            ],
            'grandchild' => [
                'grandson' => 'Grandson',
                'granddaughter' => 'Granddaughter'
            ],
            'great-grandparent' => [
                'great-grandfather' => 'Great-Grandfather',
                'great-grandmother' => 'Great-Grandmother'
            ],
            'great-grandchild' => [
                'great-grandson' => 'Great-Grandson',
                'great-granddaughter' => 'Great-Granddaughter'
            ],
            'sibling' => [
                'brother' => 'Brother',
                'sister' => 'Sister',
                'half-brother' => 'Half-Brother',
                'half-sister' => 'Half-Sister',
                'step-brother' => 'Step-Brother',
                'step-sister' => 'Step-Sister'
            ],
            'aunt-uncle' => [
                'aunt' => 'Aunt',
                'uncle' => 'Uncle',
                'great-aunt' => 'Great-Aunt',
                'great-uncle' => 'Great-Uncle'
            ],
            'cousin' => [
                'first-cousin' => 'First Cousin',
                'second-cousin' => 'Second Cousin',
                'third-cousin' => 'Third Cousin',
                'first-cousin-once-removed' => 'First Cousin Once Removed',
                'first-cousin-twice-removed' => 'First Cousin Twice Removed'
            ],
            'spouse' => [
                'husband' => 'Husband',
                'wife' => 'Wife',
                'ex-husband' => 'Ex-Husband',
                'ex-wife' => 'Ex-Wife'
            ],
            'in-law' => [
                'father-in-law' => 'Father-in-Law',
                'mother-in-law' => 'Mother-in-Law',
                'son-in-law' => 'Son-in-Law',
                'daughter-in-law' => 'Daughter-in-Law',
                'brother-in-law' => 'Brother-in-Law',
                'sister-in-law' => 'Sister-in-Law'
            ],
            'niece-nephew' => [
                'niece' => 'Niece',
                'nephew' => 'Nephew',
                'grand-niece' => 'Grand-Niece',
                'grand-nephew' => 'Grand-Nephew'
            ]
        ];

        public static function getAllRelationships() {
            $all = [];
            foreach (self::$relationships as $category => $relations) {
                $all = array_merge($all, $relations);
            }
            return $all;
        }

        public static function getRelationshipCategory($subtype) {
            foreach (self::$relationships as $category => $relations) {
                if (array_key_exists($subtype, $relations)) {
                    return $category;
                }
            }
            return 'other';
        }

        public static function getReciprocalRelationship($relationship) {
            $reciprocals = [
                'father' => 'son', 'mother' => 'daughter',
                'son' => 'father', 'daughter' => 'mother',
                'grandfather' => 'grandson', 'grandmother' => 'granddaughter',
                'grandson' => 'grandfather', 'granddaughter' => 'grandmother',
                'brother' => 'brother', 'sister' => 'sister',
                'husband' => 'wife', 'wife' => 'husband',
                'uncle' => 'nephew', 'aunt' => 'niece',
                'nephew' => 'uncle', 'niece' => 'aunt',
                'first-cousin' => 'first-cousin',
                'step-father' => 'step-son', 'step-mother' => 'step-daughter',
                'step-son' => 'step-father', 'step-daughter' => 'step-mother',
                'half-brother' => 'half-brother', 'half-sister' => 'half-sister',
                'step-brother' => 'step-brother', 'step-sister' => 'step-sister'
            ];
            return $reciprocals[$relationship] ?? null;
        }
    }
}
