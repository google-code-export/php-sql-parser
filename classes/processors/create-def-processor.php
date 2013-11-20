<?php
/**
 * col-def-processor.php
 *
 * This file implements the processor for the column definitions within the TABLE statements.
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
if (!defined('HAVE_CREATE_DEF_PROCESSOR')) {
    require_once(dirname(__FILE__) . '/abstract-processor.php');
    require_once(dirname(__FILE__) . '/index-column-list-processor.php');
    require_once(dirname(__FILE__) . '/../expression-types.php');

    /**
     * 
     * This class processes the column definitions of the TABLE statements.
     * 
     * @author arothe
     * 
     */
    class CreateDefProcessor extends AbstractProcessor {

        protected function correctExpressionType(&$expr) {
            $type = ExpressionType::EXPRESSION;
            if (!isset($expr[0]) || !isset($expr[0]['type'])) {
                return $type;
            }

            # replace the constraint type with a more descriptive one
            $type = $expr[0]['type'];
            if ($type === ExpressionType::CONSTRAINT) {
                $type = $expr[1]['type'];
                $expr[1]['type'] = ExpressionType::RESERVED;
            } else {
                $expr[0]['type'] = ExpressionType::RESERVED;
            }
            return $type;
        }

        public function process($tokens) {

            $base_expr = "";
            $prevCategory = "";
            $currCategory = "";
            $expr = array();
            $result = array();

            foreach ($tokens as $k => $token) {

                $trim = trim($token);
                $base_expr .= $token;

                if ($trim === "") {
                    continue;
                }

                $upper = strtoupper($trim);

                switch ($upper) {

                case 'CONSTRAINT':
                    $expr[] = array('type' => ExpressionType::CONSTRAINT, 'base_expr' => $trim, 'sub_tree' => false);
                    $currCategory = $prevCategory = $upper;
                    continue 2;

                case 'LIKE':
                    $expr[] = array('type' => ExpressionType::LIKE, 'base_expr' => $trim);
                    $currCategory = $prevCategory = $upper;
                    continue 2;

                case 'FOREIGN':
                    if ($prevCategory === "" || $prevCategory === "CONSTRAINT") {
                        $expr[] = array('type' => ExpressionType::FOREIGN_KEY, 'base_expr' => $trim);
                        $currCategory = $upper;
                        continue 2;
                    }
                    # else ?
                    break;

                case 'PRIMARY':
                    if ($prevCategory === "" || $prevCategory === "CONSTRAINT") {
                        # next one is KEY
                        $expr[] = array('type' => ExpressionType::PRIMARY_KEY, 'base_expr' => $trim);
                        $currCategory = $upper;
                        continue 2;
                    }
                    # else ?
                    break;

                case 'UNIQUE':
                    if ($prevCategory === "" || $prevCategory === "CONSTRAINT") {
                        # next one is KEY
                        $expr[] = array('type' => ExpressionType::UNIQUE_IDX, 'base_expr' => $trim);
                        $currCategory = $upper;
                        continue 2;
                    }
                    # else ?
                    break;

                case 'KEY':
                # the next one is an index name
                    if ($currCategory === 'PRIMARY' || $currCategory === 'FOREIGN' || $currCategory === 'UNIQUE') {
                        $expr[] = array('type' => ExpressionType::RESERVED, 'base_expr' => $trim);
                        continue 2;
                    }
                    $expr[] = array('type' => ExpressionType::INDEX, 'base_expr' => $trim);
                    $currCategory = $upper;
                    continue 2;

                case 'CHECK':
                    $expr[] = array('type' => ExpressionType::CHECK, 'base_expr' => $trim);
                    $currCategory = $upper;
                    continue 2;

                case 'INDEX':
                    if ($currCategory === 'UNIQUE' || $currCategory === 'FULLTEXT' || $currCategory === 'SPATIAL') {
                        $expr[] = array('type' => ExpressionType::RESERVED, 'base_expr' => $trim);
                        continue 2;
                    }
                    $expr[] = array('type' => ExpressionType::INDEX, 'base_expr' => $trim);
                    $currCategory = $upper;
                    continue 2;

                case 'FULLTEXT':
                    $expr[] = array('type' => ExpressionType::FULLTEXT_IDX, 'base_expr' => $trim);
                    $currCategory = $prevCategory = $upper;
                    continue 2;

                case 'SPATIAL':
                    $expr[] = array('type' => ExpressionType::SPATIAL_IDX, 'base_expr' => $trim);
                    $currCategory = $prevCategory = $upper;
                    continue 2;

                case 'WITH':
                # starts an index option
                    if ($currCategory === 'INDEX_COL_LIST') {
                        $option = array('type' => ExpressionType::RESERVED, 'base_expr' => $trim);
                        $expr[] = array('type' => ExpressionType::INDEX_PARSER,
                                        'base_expr' => substr($base_expr, 0, -strlen($token)), 'sub_tree' => array($option));
                        $base_expr = $token;
                        $currCategory = 'INDEX_PARSER';
                        continue 2;
                    }
                    break;

                case 'KEY_BLOCK_SIZE':
                # starts an index option
                    if ($currCategory === 'INDEX_COL_LIST') {
                        $option = array('type' => ExpressionType::RESERVED, 'base_expr' => $trim);
                        $expr[] = array('type' => ExpressionType::INDEX_SIZE,
                                        'base_expr' => substr($base_expr, 0, -strlen($token)),
                                        'sub_tree' => array($option));
                        $base_expr = $token;
                        $currCategory = 'INDEX_SIZE';
                        continue 2;
                    }
                    break;

                case 'USING':
                # starts an index option
                    if ($currCategory === 'INDEX_COL_LIST' || $currCategory === 'PRIMARY') {
                        $option = array('type' => ExpressionType::RESERVED, 'base_expr' => $trim);
                        $expr[] = array('base_expr' => substr($base_expr, 0, -strlen($token)), 'trim' => $trim,
                                        'category' => $currCategory, 'sub_tree' => array($option));
                        $base_expr = $token;
                        $currCategory = 'INDEX_TYPE';
                        continue 2;
                    }
                    # else ?
                    break;

                case 'REFERENCES':
                    if ($currCategory === 'INDEX_COL_LIST' && $prevCategory === 'FOREIGN') {
                        $reference = array('type' => ExpressionType::REFERENCE, 'base_expr' => $trim);
                        # TODO: the end of the statement is not sure
                        # so we must have a valid $expr, which we enhance.
                        # is it better to add properties to the expr instead sub_trees?
                        $expr[] = array('base_expr' => substr($base_expr, 0, -strlen($token)), 'sub_tree' => array($reference));
                        $currCategory = $upper;
                        
                        # table
                        # index_column-list
                        # match
                        # on-delete
                        # on-update
                    }
                    break;
                    
                case 'BTREE':
                case 'HASH':
                    if ($currCategory === 'INDEX_TYPE') {
                        $last = array_pop($expr);
                        $last['sub_tree'][] = array('type' => ExpressionType::RESERVED, 'base_expr' => $trim);
                        $expr[] = array('type' => ExpressionType::INDEX_TYPE, 'base_expr' => $base_expr,
                                        'sub_tree' => $last['sub_tree']);
                        $base_expr = $last['base_expr'] . $base_expr;
                        $currCategory = $last['category'];
                        continue 2;
                    }
                    #else
                    break;

                case '=':
                    if ($currCategory === 'INDEX_SIZE') {
                        # the optional character between KEY_BLOCK_SIZE and the numeric constant
                        $last = array_pop($expr);
                        $last['sub_tree'][] = array('type' => ExpressionType::RESERVED, 'base_expr' => $trim);
                        $expr[] = $last;
                        continue 2;
                    }
                    break;

                case 'PARSER':
                    if ($currCategory === 'INDEX_PARSER') {
                        $last = array_pop($expr);
                        $last['sub_tree'][] = array('type' => ExpressionType::RESERVED, 'base_expr' => $trim);
                        $expr[] = $last;
                        continue 2;
                    }
                    # else?
                    break;

                case 'MATCH':
                    if ($currCategory === 'REF_COL_LIST') {
                        $last = array_pop($expr);
                        $last['sub_tree'][] = array('type' => ExpressionType::RESERVED, 'base_expr' => $trim);
                        $expr[] = $last;
                        $currCategory = 'REF_MATCH';
                        continue 2;
                    }
                    # else?
                    break;    

                case 'FULL':
                case 'PARTIAL':
                case 'SIMPLE':
                    if ($currCategory === 'REF_MATCH') {
                        $last = array_pop($expr);
                        $last['sub_tree'][] = array('type' => ExpressionType::RESERVED, 'base_expr' => $trim);
                        $expr[] = $last;
                        $currCategory = 'REF_COL_LIST';
                        continue 2;
                    }
                    # else?
                    break;    
                    
                case 'ON':
                    if ($currCategory === 'REF_COL_LIST') {
                        $last = array_pop($expr);
                        $last['sub_tree'][] = array('type' => ExpressionType::RESERVED, 'base_expr' => $trim);
                        $expr[] = $last;
                        $currCategory = 'REF_ACTION';
                        continue 2;
                    }
                    # else ?    
                    break;
                    
                case 'UPDATE':
                case 'DELETE':
                    if ($currCategory === 'REF_ACTION') {
                        $last = array_pop($expr);
                        $last['sub_tree'][] = array('type' => ExpressionType::RESERVED, 'base_expr' => $trim);
                        $expr[] = $last;
                        $currCategory = 'REF_OPTION';
                        continue 2;
                    }
                    # else ?    
                    break;
                    
                case 'RESTRICT':
                case 'CASCADE':
                    if ($currCategory === 'REF_OPTION') {
                        $last = array_pop($expr);
                        $last['sub_tree'][] = array('type' => ExpressionType::RESERVED, 'base_expr' => $trim);
                        $expr[] = $last;
                        $currCategory = 'REF_OPTION';
                        continue 2;
                    }
                    # else ?
                    break;
                    
                case 'SET':
                case 'NO':
                    if ($currCategory === 'REF_OPTION') {
                        $last = array_pop($expr);
                        $last['sub_tree'][] = array('type' => ExpressionType::RESERVED, 'base_expr' => $trim);
                        $expr[] = $last;
                        $currCategory = 'REF_OPTION2';
                        continue 2;
                    }
                    # else ?
                    break;
                    
                case 'NULL':
                case 'ACTION':
                    if ($currCategory === 'REF_OPTION2') {
                        $last = array_pop($expr);
                        $last['sub_tree'][] = array('type' => ExpressionType::RESERVED, 'base_expr' => $trim);
                        $expr[] = $last;
                        $currCategory = 'REF_COL_LIST';
                        continue 2;
                    }
                    # else ?
                    break;
                                        
                    case ',':
                # this starts the next definition
                    $type = $this->correctExpressionType($expr);
                    $result['create-def'][] = array('type' => $type,
                                                    'base_expr' => trim(substr($base_expr, 0, strlen($base_expr) - 1)),
                                                    'sub_tree' => $expr);
                    $base_expr = "";
                    $expr = array();
                    break;

                default:
                    switch ($currCategory) {

                    case 'LIKE':
                    # this is the tablename after LIKE
                        $expr[] = array('type' => ExpressionType::TABLE, 'table' => $trim, 'base_expr' => $trim,
                                        'no_quotes' => $this->revokeQuotation($trim));
                        break;

                    case 'PRIMARY':
                        if ($upper[0] === '(' && substr($upper, -1) === ')') {
                            # the column list
                            $processor = new IndexColumnListProcessor();
                            $cols = $processor->process($this->removeParenthesisFromStart($trim));
                            $expr[] = array('type' => ExpressionType::COLUMN_LIST, 'base_expr' => $trim,
                                            'sub_tree' => $cols);
                            $prevCategory = $currCategory;
                            $currCategory = "INDEX_COL_LIST";
                            continue 3;
                        }
                        # else?
                        break;

                    case 'FOREIGN':
                        if ($upper[0] === '(' && substr($upper, -1) === ')') {
                            $processor = new IndexColumnListProcessor();
                            $cols = $processor->process($this->removeParenthesisFromStart($trim));
                            $expr[] = array('type' => ExpressionType::COLUMN_LIST, 'base_expr' => $trim,
                                            'sub_tree' => $cols);
                            $prevCategory = $currCategory;
                            $currCategory = "INDEX_COL_LIST";
                            continue 3;
                        }
                        # index name                        
                        $expr[] = array('type' => ExpressionType::CONSTANT, 'base_expr' => $trim);
                        continue 3;
                        
                    case 'KEY':
                    case 'UNIQUE':
                    case 'INDEX':
                        if ($upper[0] === '(' && substr($upper, -1) === ')') {
                            $processor = new IndexColumnListProcessor();
                            $cols = $processor->process($this->removeParenthesisFromStart($trim));
                            $expr[] = array('type' => ExpressionType::COLUMN_LIST, 'base_expr' => $trim,
                                            'sub_tree' => $cols);
                            $prevCategory = $currCategory;
                            $currCategory = "INDEX_COL_LIST";
                            continue 3;
                        }
                        # index name                        
                        $expr[] = array('type' => ExpressionType::CONSTANT, 'base_expr' => $trim);
                        continue 3;

                    case 'CONSTRAINT':
                    # constraint name
                        $last = array_pop($expr);
                        $last['base_expr'] = $base_expr;
                        $last['sub_tree'] = array('type' => ExpressionType::CONSTANT, 'base_expr' => $trim);
                        $expr[] = $last;
                        continue 3;

                    case 'INDEX_PARSER':
                    # index parser name
                        $last = array_pop($expr);
                        $last['sub_tree'][] = array('type' => ExpressionType::CONSTANT, 'base_expr' => $trim);
                        $expr[] = array('type' => ExpressionType::INDEX_PARSER, 'base_expr' => $base_expr,
                                        'sub_tree' => $last['sub_tree']);
                        $base_expr = $last['base_expr'] . $base_expr;
                        continue 3;

                    case 'INDEX_SIZE':
                    # index key block size numeric constant
                        $last = array_pop($expr);
                        $last['sub_tree'][] = array('type' => ExpressionType::CONSTANT, 'base_expr' => $trim);
                        $expr[] = array('type' => ExpressionType::INDEX_SIZE, 'base_expr' => $base_expr,
                                        'sub_tree' => $last['sub_tree']);
                        $base_expr = $last['base_expr'] . $base_expr;
                        continue 3;

                    case 'CHECK':
                        if ($upper[0] === '(' && substr($upper, -1) === ')') {
                            $processor = new ExpressionListProcessor();
                            $unparsed = $this->splitSQLIntoTokens($this->removeParenthesisFromStart($trim));
                            $parsed = $processor->process($unparsed);
                            $expr[] = array('type' => ExpressionType::BRACKET_EXPRESSION, 'base_expr' => $trim,
                                            'sub_tree' => $parsed);
                        }
                        # else?
                        break;

                    case 'REFERENCES':
                        $last = array_pop($expr);
                        if ($upper[0] === '(' && substr($upper, -1) === ')') {
                            # index_col_name list
                            $processor = new IndexColumnListProcessor();
                            $cols = $processor->process($this->removeParenthesisFromStart($trim));
                            $last['sub_tree'][] = array('type' => ExpressionType::COLUMN_LIST, 'base_expr' => $trim,
                                'sub_tree' => $cols);
                            $currCategory = 'REF_COL_LIST';
                            $expr[] = $last;
                            continue 3;
                        }
                        # foreign key reference table name
                        $last['sub_tree'][] = array('type' => ExpressionType::TABLE, 'table'=>$trim, 'base_expr'=>$trim, 'no_quotes'=>$this->revokeQuotation($trim));
                        $expr[] = $last;
                        continue 3;
                        
                    default:
                        break;
                    }
                    break;
                }
                $prevCategory = $currCategory;
                $currCategory = "";
            }

            $type = $this->correctExpressionType($expr);
            $result['create-def'][] = array('type' => $type, 'base_expr' => trim($base_expr), 'sub_tree' => $expr);
            return $result;
        }
    }
    define('HAVE_CREATE_DEF_PROCESSOR', 1);
}
