<?php 
  \Public\Modules\Tokens2Wealth\Classes\Wallets::createTable();  
  \Public\Modules\Tokens2Wealth\Classes\Accounts::createTable(); 
  \Public\Modules\Tokens2Wealth\Classes\Contribution::createTable(); 
  \EvoPhp\Actions\Action::add('t2wAfterCredit', '\Public\Modules\Tokens2Wealth\Classes\PendingDebits::handle'); 
  \EvoPhp\Actions\Action::add('t2wAfterCredit', '\Public\Modules\Tokens2Wealth\Classes\Loan::terminateAfterCredit');
  \EvoPhp\Actions\Action::add('evoAfterSignUp', '\Public\Modules\Tokens2Wealth\Classes\Operations::afterSignUp'); 
  \EvoPhp\Actions\Filter::add('before_update_business_cat', '\Public\Modules\Tokens2Wealth\Classes\Operations::commaSeparatedStringToArray')
?>