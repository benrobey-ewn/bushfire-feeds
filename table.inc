<div id="tablewrapper">
	<div id="tableheader">Table filter:
	       	<div class="search">
			<select id="columns" onchange="sorter.search('query')"></select>
			<input type="text" id="query" onkeyup="sorter.search('query')" />
		</div>

		<span class="details">
			<div>Records <span id="startrecord"></span>-<span id="endrecord"></span> of <span id="totalrecords"></span></div>
			<div><a href="javascript:sorter.reset()">reset</a></div>
		</span>
	</div>
 

	<div id="mtb"></div> 


	<div id="tablefooter">
		<div id="tablenav">
			<div>
			<img src="images/first.gif" width="16" height="16" alt="First Page" onclick="sorter.move(-1,true)" />
			<img src="images/previous.gif" width="16" height="16" alt="First Page" onclick="sorter.move(-1)" />
			<img src="images/next.gif" width="16" height="16" alt="First Page" onclick="sorter.move(1)" />
			<img src="images/last.gif" width="16" height="16" alt="Last Page" onclick="sorter.move(1,true)" />
		</div>
		<div><select id="pagedropdown"></select></div>
		<div><a href="javascript:sorter.showall()">view all</a></div>
		
	</div>
	
	<div id="tablelocation">
		<div>
		<select onchange="sorter.size(this.value)">
				<option value="5">5</option>
				<option value="10" selected="selected">10</option>
				<option value="20">20</option>
				<option value="50">50</option>
				<option value="100">100</option>
		 </select>
		<span>Entries Per Page</span>
		</div>

		<div class="page">Page <span id="currentpage"></span> of <span id="totalpages"></span></div>
	</div>
        
</div>
</div>