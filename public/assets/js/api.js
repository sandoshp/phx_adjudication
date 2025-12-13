const api={async getPatients(){const r=await fetch('/api/patients.php');return r.json();},
async addPatient(d){const r=await fetch('/api/patients.php',{method:'POST',body:new URLSearchParams(d)});const j=await r.json();if(j.error){alert(j.error);return false;}return true;},
async getDrugs(){const r=await fetch('/api/drugs.php');return r.json();},
async generateCaseEvents(pid){const r=await fetch('/api/case_events.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'patient_id='+encodeURIComponent(pid)});const j=await r.json();if(!j.ok){alert(j.error||'Error');return false;}return true;},
async getCaseEvents(pid){const r=await fetch('/api/case_events.php?patient_id='+encodeURIComponent(pid));return r.json();},
async getMyAdjudication(cid){const r=await fetch('/api/adjudications.php?case_event_id='+encodeURIComponent(cid));return r.json();},
async submitAdjudication(p){const r=await fetch('/api/adjudications.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(p)});const j=await r.json();if(j.error){alert(j.error);return false;}return true;}};