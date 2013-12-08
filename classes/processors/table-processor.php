<?php
/**
 * table-processor.php
 *
 * This file implements the processor for the TABLE statements.
 *
 * Copyright (c) 2010-2012, Justin Swanhart
 * with contributions by André Rothe <arothe@phosco.info, phosco@gmx.de>
 *
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 *   * Redistributions of source code must retain the above copyright notice,
 *     this list of conditions and the following disclaimer.
 *   * Redistributions in binary form must reproduce the above copyright notice,
 *     this list of conditions and the following disclaimer in the documentation
 *     and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT
 * SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
 * TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR
 * BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 */
if (!defined('HAVE_TABLE_PROCESSOR')) {
    require_once(dirname(__FILE__) . '/abstract-processor.php');
    require_once(dirname(__FILE__) . '/create-def-processor.php');
    require_once(dirname(__FILE__) . '/../expression-types.php');

    /**
     * 
     * This class processes the TABLE statements.
     * 
     * @author arothe
     * 
     */
    class TableProcessor extends AbstractProcessor {

        protected function getReservedType($token) {
            return array('type' => ExpressionType::RESERVED, 'base_expr' => $token);
        }

        protected function getConstantType($token) {
            return array('type' => ExpressionType::CONSTANT, 'base_expr' => $token);
        }

        protected function getOperatorType($token) {
            return array('type' => ExpressionType::OPERATOR, 'base_expr' => $token);
        }

        protected function clear(&$expr, &$base_expr, &$separator, &$category) {
            $expr = array();
            $base_expr = '';
            $separator = ' ';
            $category = 'CREATE_DEF';
        }

        public function process($tokens) {

            $currCategory = "TABLE_NAME";
            $result = array('base_expr' => false, 'name' => false, 'no_quotes' => false, 'create-def' => false, 'options' => false, 'like' => false, 'select' => false);
            $expr = array();
            $base_expr = '';
            $skip = 0;
            $separator = false; # first option has no separator

            foreach ($tokens as $token) {
                $trim = trim($token);
                $base_expr .= $token;

                if ($skip > 0) {
                    $skip--;
                    continue;
                }

                if ($skip < 0) {
                    break;
                }

                if ($trim === "") {
                    continue;
                }

                $upper = strtoupper($trim);
                switch ($upper) {

                case ',':
                # it is possible to separate the table options with comma!
                    if ($prevCategory === 'CREATE_DEF') {
                        $separator = ',';
                        $base_expr = "";
                    }
                    continue 2;

                case 'UNION':
                    if ($prevCategory === 'CREATE_DEF') {
                        $expr[] = $this->getReservedType($trim);
                        $currCategory = 'UNION';
                        continue 2;
                    }
                    break;

                case 'LIKE':
                # like without parenthesis
                    if ($prevCategory === 'TABLE_NAME') {
                        $currCategory = $upper;
                        continue 2;
                    }
                    break;

                case '=':
                # the optional operator
                    if ($prevCategory === 'TABLE_OPTION') {
                        $expr[] = $this->getOperatorType($trim);
                        continue 2; # don't change the category
                    }
                    break;

                case 'CHARACTER':
                    if ($prevCategory === 'CREATE_DEF') {
                        $expr[] = $this->getReservedType($trim);
                        $currCategory = 'TABLE_OPTION';
                    }
                    if ($prevCategory === 'TABLE_OPTION') {
                        # add it to the previous DEFAULT
                        $expr[] = $this->getReservedType($trim);
                        continue 2;
                    }
                    break;

                case 'SET':
                    if ($prevCategory === 'TABLE_OPTION') {
                        # add it to a previous CHARACTER
                        $expr[] = $this->getReservedType($trim);
                        $currCategory = 'CHARSET';
                        continue 2;
                    }
                    break;

                case 'COLLATE':
                    if ($prevCategory === 'TABLE_OPTION') {
                        # add it to the previous DEFAULT
                        $expr[] = $this->getReservedType($trim);
                        $currCategory = 'COLLATE';
                        continue 2;
                    }
                    break;

                case 'DIRECTORY':
                    if ($currCategory === 'INDEX_DIRECTORY' || $currCategory === 'DATA_DIRECTORY') {
                        # after INDEX or DATA
                        $expr[] = $this->getReservedType($trim);
                        continue 2;
                    }
                    break;

                case 'INDEX':
                    if ($prevCategory === 'CREATE_DEF') {
                        $expr[] = $this->getReservedType($trim);
                        $currCategory = 'INDEX_DIRECTORY';
                        continue 2;
                    }
                    break;

                case 'DATA':
                    if ($prevCategory === 'CREATE_DEF') {
                        $expr[] = $this->getReservedType($trim);
                        $currCategory = 'DATA_DIRECTORY';
                        continue 2;
                    }
                    break;

                case 'INSERT_METHOD':
                case 'DELAY_KEY_WRITE':
                case 'ROW_FORMAT':
                case 'PASSWORD':
                case 'MAX_ROWS':
                case 'MIN_ROWS':
                case 'PACK_KEYS':
                case 'CHECKSUM':
                case 'COMMENT':
                case 'CONNECTION':
                case 'AUTO_INCREMENT':
                case 'AVG_ROW_LENGTH':
                case 'ENGINE':
                case 'TYPE':
                case 'STATS_AUTO_RECALC':
                case 'STATS_PERSISTENT':
                case 'KEY_BLOCK_SIZE':
                    if ($prevCategory === 'CREATE_DEF') {
                        $expr[] = $this->getReservedType($trim);
                        $currCategory = $prevCategory = 'TABLE_OPTION';
                        continue 2;
                    }
                    break;

                case 'DYNAMIC':
                case 'FIXED':
                case 'COMPRESSED':
                case 'REDUNDANT':
                case 'COMPACT':
                case 'NO':
                case 'FIRST':
                case 'LAST':
                case 'DEFAULT':
                    if ($prevCategory === 'CREATE_DEF') {
                        # DEFAULT before CHARACTER SET and COLLATE
                        $expr[] = $this->getReservedType($trim);
                        $currCategory = 'TABLE_OPTION';
                    }
                    if ($prevCategory === 'TABLE_OPTION') {
                        # all assignments with the keywords
                        $expr[] = $this->getReservedType($trim);
                        $result['options'][] = array('type' => ExpressionType::EXPRESSION,
                                                     'base_expr' => trim($base_expr), 'separator' => $separator,
                                                     'sub_tree' => $expr);
                        $this->clear($expr, $base_expr, $separator, $currCategory);
                    }
                    break;

                case 'IGNORE':
                case 'REPLACE':
                    $expr[] = $this->getReservedType($trim);
                    $result['select'] = array('base_expr' => trim($base_expr), 'duplicates' => $trim, 'as' => false, 'sub_tree' => $expr);
                    continue 2;
                                       
                case 'AS':
                    $expr[] = $this->getReservedType($trim);
                    if (!isset($result['select']['duplicates'])) {
                        $result['select']['duplicates'] = false;
                    }
                    $result['select']['as'] = true;
                    $result['select']['base_expr'] = trim($base_expr);
                    $result['select']['sub_tree'] = $expr;
                    continue 2;    
                    
                case 'PARTITION':
                # TODO: parse partition options
                    $skip = -1;
                    break;

                default:
                    switch ($currCategory) {

                    case 'CHARSET':
                    # the charset name
                        $expr[] = $this->getConstantType($trim);
                        $result['options'][] = array('type' => ExpressionType::CHARSET,
                                                     'base_expr' => trim($base_expr), 'separator' => $separator,
                                                     'sub_tree' => $expr);
                        $this->clear($expr, $base_expr, $separator, $currCategory);
                        break;

                    case 'COLLATE':
                    # the collate name
                        $expr[] = $this->getConstantType($trim);
                        $result['options'][] = array('type' => ExpressionType::COLLATE,
                                                     'base_expr' => trim($base_expr), 'separator' => $separator,
                                                     'sub_tree' => $expr);
                        $this->clear($expr, $base_expr, $separator, $currCategory);
                        break;

                    case 'DATA_DIRECTORY':
                    # we have the directory name
                        $expr[] = $this->getConstantType($trim);
                        $result['options'][] = array('type' => ExpressionType::DIRECTORY, 'kind' => 'DATA',
                                                     'base_expr' => trim($base_expr), 'separator' => $separator,
                                                     'sub_tree' => $expr);
                        $this->clear($expr, $base_expr, $separator, $prevCategory);
                        continue 3;

                    case 'INDEX_DIRECTORY':
                    # we have the directory name
                        $expr[] = $this->getConstantType($trim);
                        $result['options'][] = array('type' => ExpressionType::DIRECTORY, 'kind' => 'INDEX',
                                                     'base_expr' => trim($base_expr), 'separator' => $separator,
                                                     'sub_tree' => $expr);
                        $this->clear($expr, $base_expr, $separator, $prevCategory);
                        continue 3;

                    case 'TABLE_NAME':
                        $result['base_expr'] = $result['name'] = $trim;
                        $result['no_quotes'] = $this->revokeQuotation($trim);
                        $this->clear($expr, $base_expr, $separator, $prevCategory);
                        break;

                    case 'LIKE':
                        $result['like'] = array('table' => $trim, 'base_expr' => $trim,
                                                'no_quotes' => $this->revokeQuotation($trim));
                        $this->clear($expr, $base_expr, $separator, $currCategory);
                        break;

                    case '':
                    # after table name
                        if ($prevCategory === 'TABLE_NAME' && $upper[0] === '(' && substr($upper, -1) === ')') {
                            $unparsed = $this->splitSQLIntoTokens($this->removeParenthesisFromStart($trim));
                            $processor = new CreateDefProcessor();
                            $coldef = $processor->process($unparsed);
                            $result['create-def'] = array('type' => ExpressionType::BRACKET_EXPRESSION,
                                                          'base_expr' => $base_expr,
                                                          'sub_tree' => $coldef['create-def']);
                            $expr = array();
                            $separator = ' ';
                            $base_expr = '';
                            $currCategory = 'CREATE_DEF';
                        }
                        break;

                    case 'UNION':
                    # TODO: this token starts and ends with parenthesis
                    # and contains a list of table names (comma-separated)
                    # split the token and add the list as subtree
                    # we must change the DefaultProcessor

                        $unparsed = $this->splitSQLIntoTokens($this->removeParenthesisFromStart($trim));
                        $expr[] = array('type' => ExpressionType::BRACKET_EXPRESSION, 'base_expr' => $trim,
                                        'sub_tree' => '***TODO***');
                        $result['options'][] = array('type' => ExpressionType::UNION, 'base_expr' => trim($base_expr),
                                                     'separator' => $separator, 'sub_tree' => $expr);
                        $this->clear($expr, $base_expr, $separator, $currCategory);
                        break;

                    default:
                    # strings and numeric constants
                        $expr[] = $this->getConstantType($trim);
                        $result['options'][] = array('type' => ExpressionType::EXPRESSION,
                                                     'base_expr' => trim($base_expr), 'separator' => $separator,
                                                     'sub_tree' => $expr);
                        $this->clear($expr, $base_expr, $separator, $currCategory);
                        break;
                    }
                    break;
                }

                $prevCategory = $currCategory;
                $currCategory = "";
            }
            return $result;
        }
    }
    define('HAVE_TABLE_PROCESSOR', 1);
}
