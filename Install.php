<?php 
  \Public\Modules\Tokens2Wealth\Classes\Wallets::createTable();  
  \Public\Modules\Tokens2Wealth\Classes\Accounts::createTable(); 
  \EvoPhp\Actions\Action::add('t2wAfterCredit', '\Public\Modules\Tokens2Wealth\Classes\PendingDebits::handle'); 
  \EvoPhp\Actions\Action::add('t2wAfterCredit', '\Public\Modules\Tokens2Wealth\Classes\Loan::terminateAfterCredit'); 
?>